<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Catalog\UI\Console;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:data:sync:fandom',
    description: 'Synchronise des ressources FO76 depuis les pages Fandom via api.php.',
)]
final class SyncFandomDataCommand extends Command
{
    /**
     * @var list<string>
     */
    private const DEFAULT_PAGES = [
        'Fallout_76_recipes',
        'Fallout_76_plans/Workshop',
        'Fallout_76_plans/Armor',
        'Fallout_76_plans/Armor_mods',
        'Fallout_76_plans/Power_armor',
        'Fallout_76_plans/Power_armor_mods',
        'Fallout_76_plans/Weapons',
        'Fallout_76_plans/Weapon_mods',
    ];

    /**
     * @var array<string, string>
     */
    private const PAGE_FILE_MAP = [
        'Fallout_76_recipes' => 'recipes.json',
        'Fallout_76_plans/Workshop' => 'plans_workshop.json',
        'Fallout_76_plans/Armor' => 'plans_armor.json',
        'Fallout_76_plans/Armor_mods' => 'plans_armor_mods.json',
        'Fallout_76_plans/Power_armor' => 'plans_power_armor.json',
        'Fallout_76_plans/Power_armor_mods' => 'plans_power_armor_mods.json',
        'Fallout_76_plans/Weapons' => 'plans_weapons.json',
        'Fallout_76_plans/Weapon_mods' => 'plans_weapon_mods.json',
    ];

    private const API_URL = 'https://fallout.fandom.com/api.php';
    private const MIN_DELAY_US = 150000;
    private const MAX_DELAY_US = 350000;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('page', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Pages Fandom (format Fallout_76_recipes, Fallout_76_plans/Workshop, ...).')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Dossier de sortie JSON (absolu ou relatif au projet).')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Compat legacy: ancien chemin de sortie. Si present et termine par .json, seul son dossier est conserve.')
            ->addOption('no-delay', null, InputOption::VALUE_NONE, 'Desactive le delai naturel entre les requetes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fandom data sync');

        $client = $this->httpClient->withOptions([
            'timeout' => 25,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'f76-data-sync-experimentation/1.0 (+https://github.com/Grandpere/f76-tools)',
            ],
        ]);

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');
        $pages = $this->resolvePages($input->getOption('page'));
        $outputDir = $this->resolveOutputDirectory(
            $projectDir,
            $input->getOption('output-dir'),
            $input->getOption('output')
        );
        $useDelay = !(bool) $input->getOption('no-delay');

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Impossible de creer le dossier de sortie: %s', $outputDir));

            return Command::FAILURE;
        }

        $pageSummaries = [];
        $resourcesTotal = 0;
        $generatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);

        try {
            foreach ($pages as $page) {
                if ($useDelay) {
                    $this->naturalDelay();
                }

                $sectionsPayload = $this->requestJson($client, [
                    'action' => 'parse',
                    'page' => $page,
                    'prop' => 'sections',
                    'formatversion' => '2',
                    'format' => 'json',
                ]);
                $sections = $this->extractSections($sectionsPayload);

                $pageResources = [];
                foreach ($sections as $section) {
                    if ($useDelay) {
                        $this->naturalDelay();
                    }

                    $sectionPayload = $this->requestJson($client, [
                        'action' => 'parse',
                        'page' => $page,
                        'prop' => 'text',
                        'section' => $section['index'],
                        'formatversion' => '2',
                        'format' => 'json',
                    ]);

                    $sectionHtml = $this->extractSectionHtml($sectionPayload);
                    $sectionResources = $this->extractResourcesFromHtml($sectionHtml, (string) $section['line']);
                    $pageResources = array_merge($pageResources, $sectionResources);
                }

                $deduplicated = $this->deduplicateAndMerge($pageResources);
                usort(
                    $deduplicated,
                    static fn (array $left, array $right): int => [$left['type'], $left['slug']] <=> [$right['type'], $right['slug']]
                );

                $fileName = $this->resolvePageFileName($page);
                $filePath = $outputDir.'/'.$fileName;
                $pageUrl = sprintf('https://fallout.fandom.com/wiki/%s', str_replace(' ', '_', $page));

                $pagePayload = [
                    'generated_at' => $generatedAt,
                    'source' => 'fallout.fandom.com',
                    'page' => $page,
                    'url' => $pageUrl,
                    'sections_count' => count($sections),
                    'resources_count' => count($deduplicated),
                    'resources' => $deduplicated,
                ];
                $this->writeJson($filePath, $pagePayload);

                $resourcesTotal += count($deduplicated);
                $pageSummaries[] = [
                    'page' => $page,
                    'url' => $pageUrl,
                    'file' => $fileName,
                    'sections_count' => count($sections),
                    'resources_count' => count($deduplicated),
                ];

                $io->text(sprintf('%s: %d sections, %d resources -> %s', $page, count($sections), count($deduplicated), $fileName));
            }
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $indexPath = $outputDir.'/index.json';
        $indexPayload = [
            'generated_at' => $generatedAt,
            'source' => 'fallout.fandom.com',
            'pages_count' => count($pageSummaries),
            'resources_total' => $resourcesTotal,
            'pages' => $pageSummaries,
        ];
        $this->writeJson($indexPath, $indexPayload);

        $io->newLine();
        $io->definitionList(
            ['Pages' => (string) count($pageSummaries)],
            ['Resources total' => (string) $resourcesTotal],
            ['Output directory' => str_starts_with($outputDir, $projectDir) ? str_replace($projectDir.'/', '', $outputDir) : $outputDir],
            ['Index' => str_starts_with($indexPath, $projectDir) ? str_replace($projectDir.'/', '', $indexPath) : $indexPath],
        );
        $io->success('Sync Fandom terminee.');

        return Command::SUCCESS;
    }

    /**
     * @param mixed $pageOption
     *
     * @return list<string>
     */
    private function resolvePages(mixed $pageOption): array
    {
        if (!is_array($pageOption)) {
            return self::DEFAULT_PAGES;
        }

        $pages = [];
        foreach ($pageOption as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $page = trim((string) $value);
            if ('' !== $page) {
                $pages[] = $page;
            }
        }

        return [] === $pages ? self::DEFAULT_PAGES : array_values(array_unique($pages));
    }

    private function resolveOutputDirectory(string $projectDir, mixed $outputDirOption, mixed $legacyOutputOption): string
    {
        $value = null;
        if (is_scalar($outputDirOption) && '' !== trim((string) $outputDirOption)) {
            $value = trim((string) $outputDirOption);
        } elseif (is_scalar($legacyOutputOption) && '' !== trim((string) $legacyOutputOption)) {
            $value = trim((string) $legacyOutputOption);
            if (str_ends_with($value, '.json')) {
                $value = dirname($value);
            }
        }

        if (null === $value) {
            return $projectDir.'/data/plan_recipes_pages';
        }

        if (str_starts_with($value, '/')) {
            return rtrim($value, '/');
        }

        return $projectDir.'/'.trim($value, '/');
    }

    private function resolvePageFileName(string $page): string
    {
        if (isset(self::PAGE_FILE_MAP[$page])) {
            return self::PAGE_FILE_MAP[$page];
        }

        $fallback = strtolower($page);
        $fallback = str_replace(['/', ' '], '_', $fallback);
        $fallback = preg_replace('/[^a-z0-9_]+/', '_', $fallback);
        $fallback = trim((string) $fallback, '_');

        return '' === $fallback ? 'page.json' : $fallback.'.json';
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(HttpClientInterface $client, array $query): array
    {
        try {
            $response = $client->request('GET', self::API_URL, ['query' => $query]);
            $statusCode = $response->getStatusCode();
            if (200 !== $statusCode) {
                throw new RuntimeException(sprintf('Fandom API returned HTTP %d for page "%s".', $statusCode, (string) ($query['page'] ?? 'unknown')));
            }

            /** @var array<string, mixed> $decoded */
            $decoded = $response->toArray(false);
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                $message = is_scalar($decoded['error']['info'] ?? null) ? (string) $decoded['error']['info'] : 'Unknown API error.';
                throw new RuntimeException(sprintf('Fandom API error: %s', $message));
            }

            return $decoded;
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException(sprintf('Fandom API request failed: %s', $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{index:string, line:string}>
     */
    private function extractSections(array $payload): array
    {
        $parse = $payload['parse'] ?? null;
        if (!is_array($parse)) {
            throw new RuntimeException('Fandom payload missing "parse" object.');
        }

        $sections = $parse['sections'] ?? null;
        if (!is_array($sections)) {
            return [];
        }

        $result = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $index = $section['index'] ?? null;
            $line = $section['line'] ?? null;
            if (!is_scalar($index) || !is_scalar($line)) {
                continue;
            }

            $result[] = [
                'index' => (string) $index,
                'line' => trim((string) $line),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSectionHtml(array $payload): string
    {
        $parse = $payload['parse'] ?? null;
        if (!is_array($parse)) {
            throw new RuntimeException('Fandom payload missing "parse" object.');
        }

        $text = $parse['text'] ?? null;
        if (!is_string($text)) {
            throw new RuntimeException('Fandom payload missing section HTML.');
        }

        return $text;
    }

    /**
     * @return list<array{
     *     type:string,
     *     slug:string,
     *     title:string,
     *     section:string,
     *     columns:array<string, string>,
     *     sources:list<array{section:string, table_index:int, row_index:int}>
     * }>
     */
    private function extractResourcesFromHtml(string $html, string $sectionLabel): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $resources = [];
        $seenKeys = [];

        /** @var DOMNodeList<DOMNode>|false $tables */
        $tables = $xpath->query('//table');
        if (false !== $tables) {
            $tableIndex = 0;
            foreach ($tables as $tableNode) {
                ++$tableIndex;
                if (!$tableNode instanceof DOMElement) {
                    continue;
                }

                $headers = [];
                $rowIndex = 0;
                /** @var DOMNodeList<DOMNode>|false $rows */
                $rows = (new DOMXPath($dom))->query('.//tr', $tableNode);
                if (false === $rows) {
                    continue;
                }

                foreach ($rows as $rowNode) {
                    ++$rowIndex;
                    if (!$rowNode instanceof DOMElement) {
                        continue;
                    }

                    $thNodes = $this->queryNodes($xpath, './th', $rowNode);
                    $tdNodes = $this->queryNodes($xpath, './td', $rowNode);

                    if (0 === count($tdNodes) && count($thNodes) > 0) {
                        $headers = $this->extractHeaderLabels($thNodes);
                        continue;
                    }

                    if (0 === count($tdNodes)) {
                        continue;
                    }

                    $columns = $this->extractColumnsFromCells($tdNodes, $headers);
                    $linkedResources = $this->extractResourcesFromCells($xpath, $tdNodes, $sectionLabel, $columns, $tableIndex, $rowIndex);
                    foreach ($linkedResources as $resource) {
                        $seenKeys[$resource['type'].'|'.$resource['slug']] = true;
                        $resources[] = $resource;
                    }
                }
            }
        }

        // Fallback: links outside tables.
        /** @var DOMNodeList<DOMNode>|false $links */
        $links = $xpath->query('//a[@href]');
        if (false !== $links) {
            foreach ($links as $linkNode) {
                if (!$linkNode instanceof DOMElement) {
                    continue;
                }
                $resource = $this->resourceFromLink($linkNode, $sectionLabel, [], 0, 0);
                if (null !== $resource) {
                    $key = $resource['type'].'|'.$resource['slug'];
                    if (isset($seenKeys[$key])) {
                        continue;
                    }
                    $resources[] = $resource;
                }
            }
        }

        return $resources;
    }

    /**
     * @param list<DOMNode> $nodes
     *
     * @return list<string>
     */
    private function extractHeaderLabels(array $nodes): array
    {
        $headers = [];
        foreach ($nodes as $index => $node) {
            $label = $this->normalizeText($node->textContent ?? '');
            if ('' === $label) {
                $label = 'column_'.($index + 1);
            }
            $headers[] = $label;
        }

        return $headers;
    }

    /**
     * @param list<DOMNode> $cells
     * @param list<string>  $headers
     *
     * @return array<string, string>
     */
    private function extractColumnsFromCells(array $cells, array $headers): array
    {
        $columns = [];
        foreach ($cells as $index => $cellNode) {
            $label = $headers[$index] ?? 'column_'.($index + 1);
            $key = $this->normalizeColumnKey($label, $index + 1);
            $value = $this->normalizeText($cellNode->textContent ?? '');
            if ('' === $value) {
                continue;
            }

            if (isset($columns[$key])) {
                $columns[$key] = trim($columns[$key].' '.$value);
            } else {
                $columns[$key] = $value;
            }
        }

        return $columns;
    }

    /**
     * @param list<DOMNode>         $cells
     * @param array<string, string> $columns
     *
     * @return list<array{
     *     type:string,
     *     slug:string,
     *     title:string,
     *     section:string,
     *     columns:array<string, string>,
     *     sources:list<array{section:string, table_index:int, row_index:int}>
     * }>
     */
    private function extractResourcesFromCells(DOMXPath $xpath, array $cells, string $sectionLabel, array $columns, int $tableIndex, int $rowIndex): array
    {
        $resources = [];
        foreach ($cells as $cellNode) {
            if (!$cellNode instanceof DOMElement) {
                continue;
            }

            $links = $this->queryNodes($xpath, './/a[@href]', $cellNode);
            foreach ($links as $linkNode) {
                if (!$linkNode instanceof DOMElement) {
                    continue;
                }

                $resource = $this->resourceFromLink($linkNode, $sectionLabel, $columns, $tableIndex, $rowIndex);
                if (null !== $resource) {
                    $resources[] = $resource;
                }
            }
        }

        return $resources;
    }

    /**
     * @param array<string, string> $columns
     *
     * @return array{
     *     type:string,
     *     slug:string,
     *     title:string,
     *     section:string,
     *     columns:array<string, string>,
     *     sources:list<array{section:string, table_index:int, row_index:int}>
     * }|null
     */
    private function resourceFromLink(DOMElement $linkNode, string $sectionLabel, array $columns, int $tableIndex, int $rowIndex): ?array
    {
        $href = (string) $linkNode->getAttribute('href');
        if (!str_starts_with($href, '/wiki/')) {
            return null;
        }

        $slug = urldecode(substr($href, strlen('/wiki/')));
        $slug = trim($slug);
        if ('' === $slug) {
            return null;
        }

        $resourceType = null;
        if (str_starts_with($slug, 'Recipe:')) {
            $resourceType = 'recipe';
        } elseif (str_starts_with($slug, 'Plan:')) {
            $resourceType = 'plan';
        }

        if (null === $resourceType) {
            return null;
        }

        $title = $this->normalizeText($linkNode->textContent ?? '');
        if ('' === $title) {
            $title = $slug;
        }

        return [
            'type' => $resourceType,
            'slug' => $slug,
            'title' => $title,
            'section' => $sectionLabel,
            'columns' => $columns,
            'sources' => [[
                'section' => $sectionLabel,
                'table_index' => $tableIndex,
                'row_index' => $rowIndex,
            ]],
        ];
    }

    /**
     * @param list<array{
     *     type:string,
     *     slug:string,
     *     title:string,
     *     section:string,
     *     columns:array<string, string>,
     *     sources:list<array{section:string, table_index:int, row_index:int}>
     * }> $resources
     *
     * @return list<array{
     *     type:string,
     *     slug:string,
     *     title:string,
     *     section:string,
     *     columns:array<string, string>,
     *     sources:list<array{section:string, table_index:int, row_index:int}>
     * }>
     */
    private function deduplicateAndMerge(array $resources): array
    {
        $indexed = [];
        foreach ($resources as $resource) {
            $key = $resource['type'].'|'.$resource['slug'];
            if (!isset($indexed[$key])) {
                $indexed[$key] = $resource;
                continue;
            }

            foreach ($resource['columns'] as $columnKey => $columnValue) {
                if (!isset($indexed[$key]['columns'][$columnKey]) || '' === $indexed[$key]['columns'][$columnKey]) {
                    $indexed[$key]['columns'][$columnKey] = $columnValue;
                }
            }

            foreach ($resource['sources'] as $source) {
                $exists = false;
                foreach ($indexed[$key]['sources'] as $existingSource) {
                    if ($existingSource['section'] === $source['section']
                        && $existingSource['table_index'] === $source['table_index']
                        && $existingSource['row_index'] === $source['row_index']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $indexed[$key]['sources'][] = $source;
                }
            }
        }

        return array_values($indexed);
    }

    private function normalizeColumnKey(string $label, int $position): string
    {
        $normalized = strtolower(trim($label));

        // Canonical keys for most common table columns.
        if (str_contains($normalized, 'form id')) {
            return 'form_id';
        }
        if (str_contains($normalized, 'weight') || str_contains($normalized, 'poids') || str_contains($normalized, 'gewicht')) {
            return 'weight';
        }
        if (str_contains($normalized, 'value') || str_contains($normalized, 'valeur') || str_contains($normalized, 'wert')) {
            return 'value';
        }
        if (str_contains($normalized, 'location') || str_contains($normalized, 'emplacement') || str_contains($normalized, 'ort')) {
            return 'location';
        }
        if (str_contains($normalized, 'item') || str_contains($normalized, 'plan') || str_contains($normalized, 'recipe')) {
            return 'name';
        }

        $slug = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $slug = trim((string) $slug, '_');

        return '' === $slug ? 'column_'.$position : $slug;
    }

    private function normalizeText(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return '' === $normalized || null === $normalized ? '' : $normalized;
    }

    /**
     * @return list<DOMNode>
     */
    private function queryNodes(DOMXPath $xpath, string $expression, DOMNode $contextNode): array
    {
        $result = $xpath->query($expression, $contextNode);
        if (false === $result) {
            return [];
        }

        $nodes = [];
        foreach ($result as $node) {
            if (!$node instanceof DOMNode) {
                continue;
            }
            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === file_put_contents($path, $json.PHP_EOL)) {
            throw new RuntimeException(sprintf('Impossible d ecrire le fichier de sortie: %s', $path));
        }
    }

    private function naturalDelay(): void
    {
        usleep(random_int(self::MIN_DELAY_US, self::MAX_DELAY_US));
    }
}
