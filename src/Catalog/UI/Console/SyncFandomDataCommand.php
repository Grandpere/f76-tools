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
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Chemin de sortie JSON (absolu ou relatif au projet).')
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
        $outputPath = $this->resolveOutputPath($projectDir, $input->getOption('output'));
        $useDelay = !(bool) $input->getOption('no-delay');

        $allResources = [];
        $pageSummaries = [];

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

                $deduplicated = $this->deduplicateByKey($pageResources);
                foreach ($deduplicated as $resource) {
                    $allResources[] = $resource;
                }

                $pageSummaries[] = [
                    'page' => $page,
                    'url' => sprintf('https://fallout.fandom.com/wiki/%s', str_replace(' ', '_', $page)),
                    'sections_count' => count($sections),
                    'resources_count' => count($deduplicated),
                ];

                $io->text(sprintf('%s: %d sections, %d resources', $page, count($sections), count($deduplicated)));
            }
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $allResources = $this->deduplicateByKey($allResources);
        usort(
            $allResources,
            static fn (array $left, array $right): int => [$left['type'], $left['slug']] <=> [$right['type'], $right['slug']]
        );

        $payload = [
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'source' => 'fallout.fandom.com',
            'pages' => $pageSummaries,
            'resources_total' => count($allResources),
            'resources' => $allResources,
        ];

        $targetDir = dirname($outputPath);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            $io->error(sprintf('Impossible de creer le dossier de sortie: %s', $targetDir));

            return Command::FAILURE;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === file_put_contents($outputPath, $json.PHP_EOL)) {
            $io->error(sprintf('Impossible d ecrire le fichier de sortie: %s', $outputPath));

            return Command::FAILURE;
        }

        $io->newLine();
        $io->definitionList(
            ['Pages' => (string) count($pages)],
            ['Resources total' => (string) count($allResources)],
            ['Output' => str_starts_with($outputPath, $projectDir) ? str_replace($projectDir.'/', '', $outputPath) : $outputPath],
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

    private function resolveOutputPath(string $projectDir, mixed $option): string
    {
        if (!is_scalar($option) || '' === trim((string) $option)) {
            return $projectDir.'/data/assets/fandom/fallout_fandom_resources.json';
        }

        $value = trim((string) $option);
        if (str_starts_with($value, '/')) {
            return $value;
        }

        return $projectDir.'/'.$value;
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
     * @return list<array{type:string, slug:string, title:string, section:string}>
     */
    private function extractResourcesFromHtml(string $html, string $sectionLabel): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');
        if (false === $nodes) {
            return [];
        }

        $resources = [];
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = (string) $node->attributes->getNamedItem('href')?->nodeValue;
            if (!str_starts_with($href, '/wiki/')) {
                continue;
            }

            $slug = urldecode(substr($href, strlen('/wiki/')));
            $slug = trim($slug);
            if ('' === $slug) {
                continue;
            }

            $resourceType = null;
            if (str_starts_with($slug, 'Recipe:')) {
                $resourceType = 'recipe';
            } elseif (str_starts_with($slug, 'Plan:')) {
                $resourceType = 'plan';
            }

            if (null === $resourceType) {
                continue;
            }

            $title = trim($node->textContent);
            if ('' === $title) {
                $title = $slug;
            }

            $resources[] = [
                'type' => $resourceType,
                'slug' => $slug,
                'title' => $title,
                'section' => $sectionLabel,
            ];
        }

        return $resources;
    }

    /**
     * @param list<array{type:string, slug:string, title:string, section:string}> $resources
     *
     * @return list<array{type:string, slug:string, title:string, section:string}>
     */
    private function deduplicateByKey(array $resources): array
    {
        $indexed = [];
        foreach ($resources as $resource) {
            $key = $resource['type'].'|'.$resource['slug'];
            if (!isset($indexed[$key])) {
                $indexed[$key] = $resource;
            }
        }

        return array_values($indexed);
    }

    private function naturalDelay(): void
    {
        usleep(random_int(self::MIN_DELAY_US, self::MAX_DELAY_US));
    }
}
