<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Entity\ItemEntity;
use App\Translation\TranslationCatalogWriter;
use Doctrine\ORM\EntityManagerInterface;

final class ItemImportApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemImportItemRepositoryInterface $itemRepository,
        private readonly TranslationCatalogWriter $translationCatalogWriter,
        private readonly ItemImportFileContextResolver $fileContextResolver,
        private readonly ItemImportJsonFileReader $jsonFileReader,
        private readonly ItemImportItemHydrator $itemHydrator,
        private readonly ItemImportTranslationCatalogBuilder $translationCatalogBuilder,
        private readonly ItemImportContextApplier $contextApplier,
    ) {
    }

    public function import(string $rootPath, bool $dryRun, int $batchSize): ItemImportResult
    {
        $files = $this->jsonFileReader->findImportFiles($rootPath);

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

        /** @var list<string> $warnings */
        $warnings = [];
        /** @var array<string, ItemEntity> $dryRunMemory */
        $dryRunMemory = [];
        /** @var array<string, ItemEntity> $writeMemory */
        $writeMemory = [];
        /** @var array<string, string> $catalogEn */
        $catalogEn = [];
        /** @var array<string, string> $catalogDe */
        $catalogDe = [];

        $pendingFlush = 0;

        foreach ($files as $file) {
            $context = $this->fileContextResolver->resolve($file);
            if (null === $context) {
                ++$stats['skipped'];
                continue;
            }

            $payload = $this->jsonFileReader->readRows($file);
            if (!is_array($payload)) {
                $warnings[] = sprintf('Fichier ignore (JSON invalide): %s', $file);
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
                $memoryKey = sprintf('%s:%d', $type->value, $sourceId);

                if ($dryRun) {
                    $item = $dryRunMemory[$memoryKey] ?? null;
                    $isNew = null === $item;
                    $item ??= (new ItemEntity())
                        ->setType($type)
                        ->setSourceId($sourceId);
                    $dryRunMemory[$memoryKey] = $item;
                } else {
                    $item = $writeMemory[$memoryKey] ?? null;

                    if (null === $item) {
                        $item = $this->itemRepository->findOneByTypeAndSourceId($type, $sourceId);
                        $isNew = null === $item;
                        $item ??= (new ItemEntity())
                            ->setType($type)
                            ->setSourceId($sourceId);
                        $writeMemory[$memoryKey] = $item;
                    } else {
                        $isNew = false;
                    }
                }

                $this->itemHydrator->hydrate($item, $row);

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
                    $warnings[] = $contextResult['warning'];
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

        return new ItemImportResult($stats, $warnings);
    }
}
