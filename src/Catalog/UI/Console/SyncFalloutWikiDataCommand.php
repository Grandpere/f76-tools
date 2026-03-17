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

use DateTimeImmutable;
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
    name: 'app:data:sync:fallout-wiki',
    description: 'Synchronise des ressources FO76 depuis fallout.wiki via api.php.',
)]
final class SyncFalloutWikiDataCommand extends Command
{
    /**
     * @var list<string>
     */
    private const DEFAULT_PAGES = [
        'Fallout_76_Recipes',
        'Fallout_76_Workshop_Plans',
        'Fallout_76_Armor_Plans',
        'Fallout_76_Apparel_Plans',
        'Fallout_76_Armor_Mod_Plans',
        'Fallout_76_Power_Armor_Plans',
        'Fallout_76_Power_Armor_Mod_Plans',
        'Fallout_76_Weapon_Plans',
        'Fallout_76_Weapon_Mod_Plans',
    ];

    /**
     * @var array<string, string>
     */
    private const PAGE_FILE_MAP = [
        'Fallout_76_Recipes' => 'recipes.json',
        'Fallout_76_Workshop_Plans' => 'plans_workshop.json',
        'Fallout_76_Armor_Plans' => 'plans_armor.json',
        'Fallout_76_Apparel_Plans' => 'plans_apparel.json',
        'Fallout_76_Armor_Mod_Plans' => 'plans_armor_mods.json',
        'Fallout_76_Power_Armor_Plans' => 'plans_power_armor.json',
        'Fallout_76_Power_Armor_Mod_Plans' => 'plans_power_armor_mods.json',
        'Fallout_76_Weapon_Plans' => 'plans_weapons.json',
        'Fallout_76_Weapon_Mod_Plans' => 'plans_weapon_mods.json',
    ];

    private const API_URL = 'https://fallout.wiki/api.php';
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
            ->addOption('page', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Pages fallout.wiki (format Fallout_76_Recipes, Fallout_76_Workshop_Plans, ...).')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Dossier de sortie JSON (absolu ou relatif au projet).')
            ->addOption('no-delay', null, InputOption::VALUE_NONE, 'Desactive le delai naturel entre les requetes.')
            ->addOption('fail-fast', null, InputOption::VALUE_NONE, 'Arrete le sync a la premiere page en erreur au lieu de conserver les pages deja synchronisees.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fallout Wiki data sync');

        $client = $this->httpClient->withOptions([
            'timeout' => 25,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'f76-data-sync-experimentation/1.0 (+https://github.com/Grandpere/f76-tools)',
            ],
        ]);

        $projectDir = rtrim($this->kernel->getProjectDir(), '/');
        $pages = $this->resolvePages($input->getOption('page'));
        $outputDir = $this->resolveOutputDirectory($projectDir, $input->getOption('output-dir'));
        $useDelay = !(bool) $input->getOption('no-delay');
        $failFast = (bool) $input->getOption('fail-fast');

        if (!is_dir($outputDir) && !mkdir($outputDir, 0o775, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Impossible de creer le dossier de sortie: %s', $outputDir));

            return Command::FAILURE;
        }

        $pageSummaries = [];
        $resourcesTotal = 0;
        $generatedAt = new DateTimeImmutable()->format(DATE_ATOM);
        $pageErrors = [];

        foreach ($pages as $page) {
            try {
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
                    static fn (array $left, array $right): int => [$left['type'], $left['slug']] <=> [$right['type'], $right['slug']],
                );

                $fileName = $this->resolvePageFileName($page);
                $filePath = $outputDir.'/'.$fileName;
                $pageUrl = sprintf('https://fallout.wiki/wiki/%s', str_replace(' ', '_', $page));

                $pagePayload = [
                    'generated_at' => $generatedAt,
                    'source' => 'fallout.wiki',
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
            } catch (RuntimeException $exception) {
                $pageUrl = sprintf('https://fallout.wiki/wiki/%s', str_replace(' ', '_', $page));
                $pageErrors[] = [
                    'page' => $page,
                    'url' => $pageUrl,
                    'message' => $exception->getMessage(),
                ];

                if ($failFast) {
                    $io->error($exception->getMessage());

                    return Command::FAILURE;
                }

                $io->warning(sprintf('%s -> %s', $page, $exception->getMessage()));
            }
        }

        $indexPath = $outputDir.'/index.json';
        $indexPayload = [
            'generated_at' => $generatedAt,
            'source' => 'fallout.wiki',
            'pages_attempted_count' => count($pages),
            'pages_count' => count($pageSummaries),
            'pages_failed_count' => count($pageErrors),
            'resources_total' => $resourcesTotal,
            'pages' => $pageSummaries,
            'page_errors' => $pageErrors,
        ];
        $this->writeJson($indexPath, $indexPayload);

        $io->newLine();
        $io->definitionList(
            ['Pages' => (string) count($pageSummaries)],
            ['Pages failed' => (string) count($pageErrors)],
            ['Resources total' => (string) $resourcesTotal],
            ['Output directory' => str_starts_with($outputDir, $projectDir) ? str_replace($projectDir.'/', '', $outputDir) : $outputDir],
            ['Index' => str_starts_with($indexPath, $projectDir) ? str_replace($projectDir.'/', '', $indexPath) : $indexPath],
        );
        if ([] !== $pageErrors) {
            $io->warning('Sync fallout.wiki terminee avec pages en erreur. Les pages reussies et l index partiel ont ete conserves.');

            return Command::FAILURE;
        }

        $io->success('Sync fallout.wiki terminee.');

        return Command::SUCCESS;
    }

    /**
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

    private function resolveOutputDirectory(string $projectDir, mixed $outputDirOption): string
    {
        if (is_scalar($outputDirOption) && '' !== trim((string) $outputDirOption)) {
            $value = trim((string) $outputDirOption);

            if (str_starts_with($value, '/')) {
                return rtrim($value, '/');
            }

            return $projectDir.'/'.trim($value, '/');
        }

        return $projectDir.'/data/sources/fallout_wiki/plan_recipes';
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
                throw new RuntimeException(sprintf('Fallout Wiki API returned HTTP %d for page "%s".', $statusCode, (string) ($query['page'] ?? 'unknown')));
            }

            /** @var array<string, mixed> $decoded */
            $decoded = $response->toArray(false);
            if (isset($decoded['error']) && is_array($decoded['error'])) {
                $message = is_scalar($decoded['error']['info'] ?? null) ? (string) $decoded['error']['info'] : 'Unknown API error.';
                throw new RuntimeException(sprintf('Fallout Wiki API error: %s', $message));
            }

            return $decoded;
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException(sprintf('Fallout Wiki API request failed: %s', $exception->getMessage()), 0, $exception);
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
            throw new RuntimeException('Fallout Wiki payload missing "parse" object.');
        }

        $sections = $parse['sections'] ?? null;
        if (!is_array($sections)) {
            return [];
        }

        $normalized = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $index = $section['index'] ?? null;
            $line = $section['line'] ?? null;
            if (!is_scalar($index) || !is_scalar($line)) {
                continue;
            }

            $normalized[] = [
                'index' => (string) $index,
                'line' => trim((string) $line),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSectionHtml(array $payload): string
    {
        $parse = $payload['parse'] ?? null;
        if (!is_array($parse)) {
            throw new RuntimeException('Fallout Wiki payload missing "parse" object.');
        }

        $text = $parse['text'] ?? null;
        if (!is_scalar($text)) {
            throw new RuntimeException('Fallout Wiki payload missing section HTML.');
        }

        return (string) $text;
    }

    /**
     * @return list<array{
     *     type:string,
     *     slug:string,
     *     name:string,
     *     section:string,
     *     columns: array<string, mixed>,
     *     availability: array<string, bool>
     * }>
     */
    private function extractResourcesFromHtml(string $html, string $sectionName): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($dom);

        /** @var DOMNodeList<DOMNode>|false $tables */
        $tables = $xpath->query('//table');
        if (false === $tables) {
            return [];
        }

        $resources = [];
        foreach ($tables as $tableNode) {
            if (!$tableNode instanceof DOMElement) {
                continue;
            }

            /** @var DOMNodeList<DOMNode>|false $rows */
            $rows = new DOMXPath($dom)->query('.//tr', $tableNode);
            if (false === $rows) {
                continue;
            }

            $headers = [];
            $rowIndex = 0;
            foreach ($rows as $rowNode) {
                if (!$rowNode instanceof DOMElement) {
                    continue;
                }

                $cells = $this->extractRowCells($rowNode);
                if ([] === $cells) {
                    continue;
                }

                if (0 === $rowIndex) {
                    $headers = $this->normalizeHeaders($cells);
                    ++$rowIndex;
                    continue;
                }

                if ([] === $headers || count($cells) < 2) {
                    ++$rowIndex;
                    continue;
                }

                $resource = $this->mapResourceRow($headers, $cells, $sectionName);
                if (null !== $resource) {
                    $resources[] = $resource;
                }

                ++$rowIndex;
            }
        }

        return $resources;
    }

    /**
     * @return list<DOMNode>
     */
    private function extractRowCells(DOMElement $rowNode): array
    {
        $cells = [];
        foreach ($rowNode->childNodes as $childNode) {
            if (!$childNode instanceof DOMElement) {
                continue;
            }

            if (!in_array(strtolower($childNode->tagName), ['th', 'td'], true)) {
                continue;
            }

            $cells[] = $childNode;
        }

        return $cells;
    }

    /**
     * @param list<DOMNode> $cells
     *
     * @return list<string>
     */
    private function normalizeHeaders(array $cells): array
    {
        $headers = [];
        foreach ($cells as $cell) {
            $headers[] = $this->normalizeHeaderName($this->extractTextContent($cell));
        }

        return $headers;
    }

    private function normalizeHeaderName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(["\xc2\xa0", "\u{a0}"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim((string) $value);

        return match ($value) {
            'name' => 'name',
            'image' => 'image',
            'acquired', 'obtained' => 'obtained',
            'value', 'value image: caps', 'value image: gold bullion' => 'value',
            'type' => 'type',
            'unlocks' => 'unlocks',
            'form id' => 'form_id',
            default => $value,
        };
    }

    /**
     * @param list<string>  $headers
     * @param list<DOMNode> $cells
     *
     * @return array{
     *     type:string,
     *     slug:string,
     *     name:string,
     *     section:string,
     *     columns: array<string, mixed>,
     *     availability: array<string, bool>
     * }|null
     */
    private function mapResourceRow(array $headers, array $cells, string $sectionName): ?array
    {
        $columns = [];
        foreach ($headers as $index => $header) {
            $cell = $cells[$index] ?? null;
            if (!$cell instanceof DOMNode) {
                continue;
            }

            $mapped = $this->extractCellValue($header, $cell);
            if (null === $mapped) {
                continue;
            }

            $columns[$header] = $mapped;
        }

        $nameRaw = $columns['name'] ?? null;
        if (!is_string($nameRaw) || '' === trim($nameRaw)) {
            return null;
        }

        $name = trim($nameRaw);
        $type = str_starts_with(strtolower($name), 'recipe:') ? 'recipe' : 'plan';

        $nameCellIndex = array_search('name', $headers, true);
        $nameCell = is_int($nameCellIndex) ? ($cells[$nameCellIndex] ?? null) : null;
        $href = $nameCell instanceof DOMNode ? $this->extractFirstHref($nameCell) : null;
        $hrefMetadata = $this->resolveWikiHrefMetadata($href);

        if (null !== $hrefMetadata) {
            $columns['wiki_url'] ??= $hrefMetadata['wiki_url'];
            $columns['source_slug'] ??= $hrefMetadata['source_slug'];
        }

        if (!isset($columns['wiki_url'])) {
            $columns['wiki_url'] = sprintf('https://fallout.wiki/wiki/%s', rawurlencode(str_replace(' ', '_', $name)));
        }

        $slug = $this->resolveResourceSlug($name, $columns);

        return [
            'type' => $type,
            'slug' => $slug,
            'name' => $name,
            'section' => $sectionName,
            'columns' => $columns,
            'availability' => [],
        ];
    }

    private function extractCellValue(string $header, DOMNode $cell): mixed
    {
        if ('image' === $header) {
            return $this->extractTitleValues($cell);
        }

        if ('name' === $header) {
            $name = $this->extractTextContent($cell);
            $href = $this->extractFirstHref($cell);
            if (null !== $href) {
                return trim($name);
            }

            return trim($name);
        }

        if ('value' === $header) {
            $value = trim($this->extractTextContent($cell));
            $titleValues = $this->extractTitleValues($cell);
            if ([] !== $titleValues) {
                $columns = ['amount' => $value, 'currency' => end($titleValues)];

                return $columns;
            }

            return $value;
        }

        $text = trim($this->extractTextContent($cell));
        $titleValues = $this->extractTitleValues($cell);

        if ([] !== $titleValues && '' !== $text) {
            return [
                'text' => $text,
                'icons' => $titleValues,
            ];
        }

        if ([] !== $titleValues) {
            return $titleValues;
        }

        return '' === $text ? null : $text;
    }

    private function extractTextContent(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/', ' ', $node->textContent ?? '') ?? '');
    }

    /**
     * @return list<string>
     */
    private function extractTitleValues(DOMNode $node): array
    {
        $values = [];

        if ($node instanceof DOMElement && $node->hasAttribute('title')) {
            $values[] = trim($node->getAttribute('title'));
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                $values = array_merge($values, $this->extractTitleValues($childNode));
            }
        }

        return array_values(array_filter(array_unique($values), static fn (string $value): bool => '' !== $value));
    }

    private function extractFirstHref(DOMNode $node): ?string
    {
        if ($node instanceof DOMElement && 'a' === strtolower($node->tagName) && $node->hasAttribute('href')) {
            return $node->getAttribute('href');
        }

        if (!$node->hasChildNodes()) {
            return null;
        }

        foreach ($node->childNodes as $childNode) {
            $href = $this->extractFirstHref($childNode);
            if (null !== $href) {
                return $href;
            }
        }

        return null;
    }

    /**
     * @return array{wiki_url: string, source_slug: string}|null
     */
    private function resolveWikiHrefMetadata(?string $href): ?array
    {
        if (null === $href) {
            return null;
        }

        $href = trim($href);
        if ('' === $href) {
            return null;
        }

        if (!str_starts_with($href, '/wiki/')) {
            return null;
        }

        $sourceSlug = urldecode(substr($href, strlen('/wiki/')));
        $sourceSlug = trim($sourceSlug);
        if ('' === $sourceSlug) {
            return null;
        }

        return [
            'wiki_url' => 'https://fallout.wiki'.$href,
            'source_slug' => $sourceSlug,
        ];
    }

    /**
     * @param list<array{
     *     type:string,
     *     slug:string,
     *     name:string,
     *     section:string,
     *     columns: array<string, mixed>,
     *     availability: array<string, bool>
     * }> $resources
     *
     * @return list<array{
     *     type:string,
     *     slug:string,
     *     name:string,
     *     section:string,
     *     columns: array<string, mixed>,
     *     availability: array<string, bool>
     * }>
     */
    private function deduplicateAndMerge(array $resources): array
    {
        $indexed = [];
        foreach ($resources as $resource) {
            $formId = $resource['columns']['form_id'] ?? null;
            $key = is_string($formId) && '' !== trim($formId)
                ? $resource['type'].'|form_id|'.strtoupper(trim($formId))
                : $resource['type'].'|slug|'.$resource['slug'];
            if (!isset($indexed[$key])) {
                $indexed[$key] = $resource;
                continue;
            }

            $indexed[$key]['columns'] = array_replace($indexed[$key]['columns'], $resource['columns']);
        }

        return array_values($indexed);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === file_put_contents($path, $encoded."\n")) {
            throw new RuntimeException(sprintf('Impossible d ecrire le fichier %s', $path));
        }
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim((string) $value, '-');
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function resolveResourceSlug(string $name, array $columns): string
    {
        $sourceSlug = $columns['source_slug'] ?? null;
        if (is_string($sourceSlug) && '' !== trim($sourceSlug)) {
            return $this->slugify($sourceSlug);
        }

        $formId = $columns['form_id'] ?? null;
        if (is_string($formId) && '' !== trim($formId)) {
            return $this->slugify($name.' '.$formId);
        }

        return $this->slugify($name);
    }

    private function naturalDelay(): void
    {
        usleep(random_int(self::MIN_DELAY_US, self::MAX_DELAY_US));
    }
}
