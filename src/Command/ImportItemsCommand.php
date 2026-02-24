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

namespace App\Command;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Repository\ItemEntityRepository;
use App\Translation\TranslationCatalogWriter;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Stringable;

#[AsCommand(
    name: 'app:items:import',
    description: 'Importe les items depuis les fichiers JSON legendary_mods et minerva.',
)]
final class ImportItemsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemEntityRepository $itemRepository,
        private readonly TranslationCatalogWriter $translationCatalogWriter,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Dossier contenant les JSON.', 'data')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse et affiche sans ecrire en base.')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'Taille de lot Doctrine.', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $pathArgRaw = $input->getArgument('path');
        if (!is_string($pathArgRaw) || '' === trim($pathArgRaw)) {
            $io->error('Argument "path" invalide.');

            return Command::INVALID;
        }

        $pathArg = $pathArgRaw;
        $rootPath = str_starts_with($pathArg, '/')
            ? $pathArg
            : rtrim($this->kernel->getProjectDir(), '/').'/'.ltrim($pathArg, '/');

        if (!is_dir($rootPath)) {
            $io->error(sprintf('Dossier introuvable: %s', $rootPath));

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');

        $batchSizeRaw = $input->getOption('batch-size');
        if (!is_scalar($batchSizeRaw) || !is_numeric((string) $batchSizeRaw)) {
            $io->error('Option --batch-size invalide.');

            return Command::INVALID;
        }
        $batchSize = max(1, (int) $batchSizeRaw);

        $files = $this->resolveJsonFiles($rootPath);
        if ([] === $files) {
            $io->warning(sprintf('Aucun fichier JSON importable trouve dans %s', $rootPath));

            return Command::SUCCESS;
        }

        $stats = [
            'files' => count($files),
            'rows' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'warnings' => 0,
            'translations_en' => 0,
            'translations_de' => 0,
        ];
        /** @var array<string, ItemEntity> $dryRunMemory */
        $dryRunMemory = [];
        /** @var array<string, ItemEntity> $writeMemory */
        $writeMemory = [];
        /** @var array<string, string> $catalogEn */
        $catalogEn = [];
        /** @var array<string, string> $catalogDe */
        $catalogDe = [];

        $io->title('Import Items');
        $io->text(sprintf('Dossier: %s', $rootPath));
        $io->text(sprintf('Mode: %s', $dryRun ? 'DRY-RUN' : 'WRITE'));

        $pendingFlush = 0;

        foreach ($files as $file) {
            $context = $this->resolveFileContext($file);
            if (null === $context) {
                ++$stats['skipped'];
                continue;
            }

            $payload = $this->decodeJsonFile($file);
            if (!is_array($payload)) {
                $io->warning(sprintf('Fichier ignore (JSON invalide): %s', $file));
                ++$stats['errors'];
                continue;
            }

            foreach ($payload as $row) {
                ++$stats['rows'];

                if (!is_array($row)) {
                    ++$stats['errors'];
                    continue;
                }

                if (!isset($row['id']) || !is_numeric($row['id'])) {
                    ++$stats['errors'];
                    continue;
                }

                $sourceId = (int) $row['id'];
                $type = $context['type'];

                if ($dryRun) {
                    $memoryKey = sprintf('%s:%d', $type->value, $sourceId);
                    $item = $dryRunMemory[$memoryKey] ?? null;
                    $isNew = null === $item;
                    $item ??= new ItemEntity()
                        ->setType($type)
                        ->setSourceId($sourceId);
                    $dryRunMemory[$memoryKey] = $item;
                } else {
                    $memoryKey = sprintf('%s:%d', $type->value, $sourceId);
                    $item = $writeMemory[$memoryKey] ?? null;

                    if (null === $item) {
                        $item = $this->itemRepository->findOneByTypeAndSourceId($type, $sourceId);
                        $isNew = null === $item;
                        $item ??= new ItemEntity()
                            ->setType($type)
                            ->setSourceId($sourceId);
                        $writeMemory[$memoryKey] = $item;
                    } else {
                        $isNew = false;
                    }
                }

                $item->setFormId($this->toNullableString($row['form_id'] ?? null));
                $item->setEditorId($this->toNullableString($row['editor_id'] ?? null));
                $item->setPrice($this->toNullableInt($row['price'] ?? null));
                $item->setPriceMinerva($this->toNullableInt($row['price_minerva'] ?? null));
                $item->setWikiUrl($this->toNullableString($row['wiki_url'] ?? null));
                $item->setTradeable($this->toNullableInt($row['tradeable'] ?? 0) === 1);
                $item->setPayload($this->normalizePayload($row));

                $nameKey = sprintf('item.%s.%d.name', strtolower($type->value), $sourceId);
                $descKey = sprintf('item.%s.%d.desc', strtolower($type->value), $sourceId);
                $item->setNameKey($nameKey);

                $nameEn = $this->toNullableString($row['name_en'] ?? null);
                $nameDe = $this->toNullableString($row['name_de'] ?? null);
                $descEn = $this->toNullableString($row['desc_en'] ?? null);
                $descDe = $this->toNullableString($row['desc_de'] ?? null);

                if (null !== $nameEn) {
                    $catalogEn[$nameKey] = $nameEn;
                } elseif (null !== $nameDe) {
                    $catalogEn[$nameKey] = $nameDe;
                } else {
                    $catalogEn[$nameKey] = sprintf('item_%d', $sourceId);
                }

                if (null !== $nameDe) {
                    $catalogDe[$nameKey] = $nameDe;
                } elseif (null !== $nameEn) {
                    $catalogDe[$nameKey] = $nameEn;
                }

                if (null !== $descEn || null !== $descDe) {
                    $item->setDescKey($descKey);
                    if (null !== $descEn) {
                        $catalogEn[$descKey] = $descEn;
                    } elseif (null !== $descDe) {
                        $catalogEn[$descKey] = $descDe;
                    }

                    if (null !== $descDe) {
                        $catalogDe[$descKey] = $descDe;
                    } elseif (null !== $descEn) {
                        $catalogDe[$descKey] = $descEn;
                    }
                } else {
                    $item->setDescKey(null);
                }

                if (ItemTypeEnum::MISC === $type) {
                    $incomingRank = $context['rank'];
                    if (null === $incomingRank) {
                        ++$stats['errors'];
                        continue;
                    }

                    if (null !== $item->getRank() && $item->getRank() !== $incomingRank) {
                        $io->warning(sprintf(
                            'Conflit rank pour MISC id=%d (%d -> %d), conservation=%d',
                            $sourceId,
                            $item->getRank(),
                            $incomingRank,
                            $item->getRank(),
                        ));
                        ++$stats['warnings'];
                    } else {
                        $item->setRank($incomingRank);
                    }
                } else {
                    $incomingListNumber = $context['listNumber'];
                    $incomingIsSpecial = $context['isSpecialList'];
                    if (null === $incomingListNumber) {
                        ++$stats['errors'];
                        continue;
                    }

                    $item->setRank(null);
                    $item->addBookList($incomingListNumber, $incomingIsSpecial);
                }

                if ($isNew) {
                    ++$stats['created'];
                } else {
                    ++$stats['updated'];
                }

                if (!$dryRun) {
                    $this->entityManager->persist($item);
                    ++$pendingFlush;
                }

                if (!$dryRun && $pendingFlush >= $batchSize) {
                    $this->entityManager->flush();
                    $pendingFlush = 0;
                }
            }
        }

        if (!$dryRun && $pendingFlush > 0) {
            $this->entityManager->flush();
        }

        $stats['translations_en'] = count($catalogEn);
        $stats['translations_de'] = count($catalogDe);

        if (!$dryRun) {
            $this->translationCatalogWriter->upsert('en', 'items', $catalogEn);
            $this->translationCatalogWriter->upsert('de', 'items', $catalogDe);
        }

        $io->newLine();
        $io->definitionList(
            ['Fichiers' => (string) $stats['files']],
            ['Lignes lues' => (string) $stats['rows']],
            ['Crees' => (string) $stats['created']],
            ['Maj' => (string) $stats['updated']],
            ['Skips' => (string) $stats['skipped']],
            ['Warnings' => (string) $stats['warnings']],
            ['Erreurs' => (string) $stats['errors']],
            ['Trads EN' => (string) $stats['translations_en']],
            ['Trads DE' => (string) $stats['translations_de']],
        );

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveJsonFiles(string $rootPath): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($rootPath)
            ->name('*.json')
            ->notName('manifest.json');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @return array{type: ItemTypeEnum, rank: int|null, listNumber: int|null, isSpecialList: bool}|null
     */
    private function resolveFileContext(string $filePath): ?array
    {
        $filename = basename($filePath);

        if (1 === preg_match('/^legendary_mods_(\d+)_\w+\.json$/', $filename, $matches)) {
            return [
                'type' => ItemTypeEnum::MISC,
                'rank' => (int) $matches[1],
                'listNumber' => null,
                'isSpecialList' => false,
            ];
        }

        if (1 === preg_match('/^minerva_(\d+)_\w+\.json$/', $filename, $matches)) {
            $minervaNumber = (int) $matches[1];
            $listNumber = (($minervaNumber - 61) % 4) + 1;
            $isSpecialList = 4 === $listNumber;

            return [
                'type' => ItemTypeEnum::BOOK,
                'rank' => null,
                'listNumber' => $listNumber,
                'isSpecialList' => $isSpecialList,
            ];
        }

        return null;
    }

    /**
     * @return array<mixed>|null
     */
    private function decodeJsonFile(string $path): ?array
    {
        try {
            $json = file_get_contents($path);
            if (false === $json) {
                return null;
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function toNullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value) && !$value instanceof Stringable) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizePayload(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
