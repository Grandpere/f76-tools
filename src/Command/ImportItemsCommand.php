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

use App\Catalog\Application\Import\ItemImportFileContextResolver;
use App\Catalog\Application\Import\ItemImportJsonFileReader;
use App\Catalog\Application\Import\ItemImportContextApplier;
use App\Catalog\Application\Import\ItemImportTranslationCatalogBuilder;
use App\Catalog\Application\Import\ItemImportValueNormalizer;
use App\Entity\ItemEntity;
use App\Repository\ItemEntityRepository;
use App\Translation\TranslationCatalogWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

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
        private readonly ItemImportFileContextResolver $fileContextResolver,
        private readonly ItemImportJsonFileReader $jsonFileReader,
        private readonly ItemImportValueNormalizer $valueNormalizer,
        private readonly ItemImportTranslationCatalogBuilder $translationCatalogBuilder,
        private readonly ItemImportContextApplier $contextApplier,
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

        $files = $this->jsonFileReader->findImportFiles($rootPath);
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
            $context = $this->fileContextResolver->resolve($file);
            if (null === $context) {
                ++$stats['skipped'];
                continue;
            }

            $payload = $this->jsonFileReader->readRows($file);
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

                $item->setFormId($this->valueNormalizer->toNullableString($row['form_id'] ?? null));
                $item->setEditorId($this->valueNormalizer->toNullableString($row['editor_id'] ?? null));
                $item->setPrice($this->valueNormalizer->toNullableInt($row['price'] ?? null));
                $item->setPriceMinerva($this->valueNormalizer->toNullableInt($row['price_minerva'] ?? null));
                $item->setWikiUrl($this->valueNormalizer->toNullableString($row['wiki_url'] ?? null));
                $item->setTradeable($this->valueNormalizer->toNullableInt($row['tradeable'] ?? 0) === 1);
                $item->setIsNew($this->valueNormalizer->toBool($row['new'] ?? null));
                $item->setDropRaid($this->valueNormalizer->toBool($row['drop_raid'] ?? null));
                $item->setDropBurningSprings($this->valueNormalizer->toBoolFromRowAny($row, [
                    'drop_burningsprings',
                    'drop_burningssprings',
                    'drop_burning_springs',
                ]));
                $item->setDropDailyOps($this->valueNormalizer->toBool($row['drop_dailyops'] ?? null));
                $item->setVendorRegs($this->valueNormalizer->toBool($row['vendor_regs'] ?? null));
                $item->setVendorSamuel($this->valueNormalizer->toBool($row['vendor_samuel'] ?? null));
                $item->setVendorMortimer($this->valueNormalizer->toBool($row['vendor_mortimer'] ?? null));
                $item->setInfoHtml($this->valueNormalizer->toNullableString($row['info'] ?? null));
                $item->setDropSourcesHtml($this->valueNormalizer->toNullableString($row['drop_sources'] ?? null));
                $item->setRelationsHtml($this->valueNormalizer->toNullableString($row['relations'] ?? null));
                $item->setPayload($this->valueNormalizer->normalizePayload($row));

                $translationData = $this->translationCatalogBuilder->build($type, $sourceId, $row);
                $item->setNameKey($translationData['nameKey']);
                $item->setDescKey($translationData['descKey']);
                $catalogEn = array_merge($catalogEn, $translationData['catalogEn']);
                $catalogDe = array_merge($catalogDe, $translationData['catalogDe']);

                $contextResult = $this->contextApplier->apply($item, $sourceId, $context);
                if (!$contextResult['valid']) {
                    ++$stats['errors'];
                    continue;
                }
                if (null !== $contextResult['warning']) {
                    $io->warning($contextResult['warning']);
                    ++$stats['warnings'];
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

}
