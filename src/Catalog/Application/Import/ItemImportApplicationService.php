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

namespace App\Catalog\Application\Import;

use App\Catalog\Application\Translation\TranslationCatalogWriter;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;

final class ItemImportApplicationService
{
    public function __construct(
        private readonly ItemImportPersistence $persistence,
        private readonly ItemImportItemRepository $itemRepository,
        private readonly TranslationCatalogWriter $translationCatalogWriter,
        private readonly ItemImportFileContextResolver $fileContextResolver,
        private readonly ItemImportSourceReader $sourceReader,
        private readonly ItemImportItemHydrator $itemHydrator,
        private readonly ItemImportTranslationCatalogBuilder $translationCatalogBuilder,
        private readonly ItemImportContextApplier $contextApplier,
    ) {
    }

    public function import(string $rootPath, bool $dryRun, int $batchSize, bool $resetBookLists): ItemImportResult
    {
        $files = $this->sourceReader->findImportFiles($rootPath);

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
        /** @var array<string, int> $ignoredMissingIdByFile */
        $ignoredMissingIdByFile = [];
        /** @var array<string, ItemEntity> $dryRunMemory */
        $dryRunMemory = [];
        /** @var array<string, ItemEntity> $writeMemory */
        $writeMemory = [];
        /** @var array<string, string> $catalogEn */
        $catalogEn = [];
        /** @var array<string, string> $catalogDe */
        $catalogDe = [];
        /** @var array<string, true> $seenProviderRefs */
        $seenProviderRefs = [];

        $pendingFlush = 0;

        if (!$dryRun && $resetBookLists) {
            $this->itemRepository->deleteAllBookLists();
        }

        foreach ($files as $file) {
            $context = $this->fileContextResolver->resolve($file);
            if (null === $context) {
                ++$stats['skipped'];
                continue;
            }

            $payload = $this->sourceReader->readRows($file);
            if (!is_array($payload)) {
                $warnings[] = sprintf('Fichier ignore (JSON invalide): %s', $file);
                ++$stats['errors'];
                continue;
            }

            /** @var array<string, true> $seenInCurrentFile */
            $seenInCurrentFile = [];
            foreach ($payload as $row) {
                ++$stats['rows'];

                if (!is_array($row)) {
                    ++$stats['errors'];
                    continue;
                }
                $row = $this->normalizeImportRow($row);

                if (!isset($row['id']) || !is_numeric($row['id'])) {
                    if ($this->shouldIgnoreMissingSourceId($context, $row)) {
                        ++$stats['skipped'];
                        $ignoredMissingIdByFile[basename($file)] = ($ignoredMissingIdByFile[basename($file)] ?? 0) + 1;
                        continue;
                    }

                    ++$stats['errors'];
                    continue;
                }

                $sourceId = (int) $row['id'];
                $formIdLabel = is_scalar($row['form_id'] ?? null) ? (string) $row['form_id'] : 'unknown';
                $seenKey = sprintf('%s:%d', $context->type->value, $sourceId);
                if (isset($seenInCurrentFile[$seenKey])) {
                    if ($this->shouldSkipDuplicateSourceRow($context, $row, $seenProviderRefs)) {
                        $warnings[] = sprintf(
                            'Doublon form_id detecte dans %s pour provider=%s form_id=%s (ignore)',
                            basename($file),
                            $context->sourceProvider,
                            $formIdLabel,
                        );
                        ++$stats['warnings'];
                        ++$stats['skipped'];

                        continue;
                    }

                    $warnings[] = sprintf(
                        'Doublon detecte dans %s pour %s id=%d (conserve)',
                        basename($file),
                        $context->type->value,
                        $sourceId,
                    );
                    ++$stats['warnings'];
                }
                $seenInCurrentFile[$seenKey] = true;

                $providerRefKey = $this->buildProviderRefKey($context, $row);
                if (null !== $providerRefKey) {
                    if (isset($seenProviderRefs[$providerRefKey])) {
                        $warnings[] = sprintf(
                            'Doublon form_id detecte pour provider=%s form_id=%s (ignore)',
                            $context->sourceProvider,
                            $formIdLabel,
                        );
                        ++$stats['warnings'];
                        ++$stats['skipped'];

                        continue;
                    }

                    $seenProviderRefs[$providerRefKey] = true;
                }

                $type = $context->type;
                $memoryKey = sprintf('%s:%d', $type->value, $sourceId);
                $externalSource = $this->itemHydrator->buildExternalSourceData($context->sourceProvider, $row, $sourceId);

                if ($dryRun) {
                    $item = $dryRunMemory[$memoryKey] ?? null;
                    $isNew = null === $item;
                    $item ??= new ItemEntity()
                        ->setType($type)
                        ->setSourceId($sourceId);
                    $dryRunMemory[$memoryKey] = $item;
                } else {
                    [$item, $isNew, $duplicate] = $this->resolveWriteTarget($type, $sourceId, $externalSource['externalRef'], $writeMemory);

                    if ($duplicate instanceof ItemEntity) {
                        $this->mergeDuplicateBookItem($item, $duplicate);

                        foreach ($writeMemory as $cachedKey => $cachedItem) {
                            if ($cachedItem === $duplicate) {
                                $writeMemory[$cachedKey] = $item;
                            }
                        }
                        $writeMemory[sprintf('%s:%d', $duplicate->getType()->value, $duplicate->getSourceId())] = $item;
                    }
                }

                $this->itemHydrator->hydrate($item, $row);
                $item->upsertExternalSource(
                    $context->sourceProvider,
                    $externalSource['externalRef'],
                    $externalSource['externalUrl'],
                    $externalSource['metadata'],
                );

                $translationData = $this->translationCatalogBuilder->build($type, $item->getSourceId(), $row);
                $item->setNameKey($translationData->nameKey);
                $item->setDescKey($translationData->descKey);
                $item->setNoteKey($translationData->noteKey);
                $catalogEn = array_merge($catalogEn, $translationData->catalogEn);
                $catalogDe = array_merge($catalogDe, $translationData->catalogDe);

                $contextResult = $this->contextApplier->apply($item, $sourceId, $context);
                if (!$contextResult->valid) {
                    ++$stats['errors'];
                    continue;
                }
                if (null !== $contextResult->warning) {
                    $warnings[] = $contextResult->warning;
                    ++$stats['warnings'];
                }

                if ($isNew) {
                    ++$stats['created'];
                } else {
                    ++$stats['updated'];
                }

                if (!$dryRun) {
                    $this->persistence->persist($item);
                    ++$pendingFlush;
                }

                if (!$dryRun && $pendingFlush >= $batchSize) {
                    $this->persistence->flush();
                    $pendingFlush = 0;
                }
            }
        }

        foreach ($ignoredMissingIdByFile as $fileName => $ignoredCount) {
            $warnings[] = sprintf(
                'Lignes ignorees dans %s: %d row(s) sans form_id exploitable',
                $fileName,
                $ignoredCount,
            );
            ++$stats['warnings'];
        }

        if (!$dryRun && $pendingFlush > 0) {
            $this->persistence->flush();
        }

        $stats['translations_en'] = count($catalogEn);
        $stats['translations_de'] = count($catalogDe);

        if (!$dryRun) {
            $this->translationCatalogWriter->upsert('en', 'items', $catalogEn);
            $this->translationCatalogWriter->upsert('de', 'items', $catalogDe);
        }

        return new ItemImportResult($stats, $warnings);
    }

    /**
     * @param array<string, ItemEntity> $writeMemory
     *
     * @return array{0:ItemEntity,1:bool,2:?ItemEntity}
     */
    private function resolveWriteTarget(ItemTypeEnum $type, int $sourceId, string $externalRef, array &$writeMemory): array
    {
        $memoryKey = sprintf('%s:%d', $type->value, $sourceId);
        $item = $writeMemory[$memoryKey] ?? null;
        if ($item instanceof ItemEntity) {
            return [$item, false, null];
        }

        $itemBySourceId = $this->itemRepository->findOneByTypeAndSourceId($type, $sourceId);
        $candidateItems = [];
        if ($itemBySourceId instanceof ItemEntity) {
            $candidateItems[] = $itemBySourceId;
        }

        if (ItemTypeEnum::BOOK === $type && '' !== $externalRef && !str_starts_with($externalRef, 'source_id:')) {
            foreach ($this->itemRepository->findBooksByExternalRef($externalRef) as $candidateItem) {
                if (!in_array($candidateItem, $candidateItems, true)) {
                    $candidateItems[] = $candidateItem;
                }
            }
        }

        if ([] === $candidateItems) {
            $item = new ItemEntity()
                ->setType($type)
                ->setSourceId($sourceId);
            $writeMemory[$memoryKey] = $item;

            return [$item, true, null];
        }

        $preferredCatalogCandidate = null;
        foreach ($candidateItems as $candidateItem) {
            if ($this->hasNonNukaknightsExternalSource($candidateItem)) {
                $preferredCatalogCandidate = $candidateItem;
                break;
            }
        }

        $item = $preferredCatalogCandidate ?? $this->choosePreferredImportItem($candidateItems);
        $duplicate = null;
        foreach ($candidateItems as $candidateItem) {
            if ($candidateItem !== $item) {
                $duplicate = $candidateItem;
                break;
            }
        }

        $writeMemory[$memoryKey] = $item;

        return [$item, false, $duplicate];
    }

    /**
     * @param list<ItemEntity> $candidateItems
     */
    private function choosePreferredImportItem(array $candidateItems): ItemEntity
    {
        $nonNukaknightsCandidates = array_values(array_filter(
            $candidateItems,
            fn (ItemEntity $item): bool => $this->hasNonNukaknightsExternalSource($item),
        ));
        if ([] !== $nonNukaknightsCandidates) {
            $candidateItems = $nonNukaknightsCandidates;
        }

        usort($candidateItems, function (ItemEntity $left, ItemEntity $right): int {
            $leftScore = $this->scoreImportCandidate($left);
            $rightScore = $this->scoreImportCandidate($right);
            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            return ($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX);
        });

        return $candidateItems[0];
    }

    private function scoreImportCandidate(ItemEntity $item): int
    {
        $score = 0;
        $providerCount = 0;
        foreach ($item->getExternalSources() as $externalSource) {
            ++$providerCount;
        }

        $score += $providerCount * 10;
        if ($this->hasNonNukaknightsExternalSource($item)) {
            $score += 100;
        }
        if (!$item->getBookLists()->isEmpty()) {
            $score += 5;
        }

        return $score;
    }

    private function hasNonNukaknightsExternalSource(ItemEntity $item): bool
    {
        foreach ($item->getExternalSources() as $externalSource) {
            if ('nukaknights' !== $externalSource->getProvider()) {
                return true;
            }
        }

        return false;
    }

    private function mergeDuplicateBookItem(ItemEntity $keeper, ItemEntity $duplicate): void
    {
        if ($keeper === $duplicate) {
            return;
        }

        foreach ($duplicate->getBookLists() as $bookList) {
            $keeper->addBookList($bookList->getListNumber(), $bookList->isSpecialList());
        }

        foreach ($duplicate->getExternalSources() as $externalSource) {
            $keeper->upsertExternalSource(
                $externalSource->getProvider(),
                $externalSource->getExternalRef(),
                $externalSource->getExternalUrl(),
                $externalSource->getMetadata(),
            );
        }

        if (null === $keeper->getPrice()) {
            $keeper->setPrice($duplicate->getPrice());
        }
        if (null === $keeper->getPriceMinerva()) {
            $keeper->setPriceMinerva($duplicate->getPriceMinerva());
        }

        $keeper->setIsNew($keeper->isNew() || $duplicate->isNew());
        $keeper->setDropRaid($keeper->isDropRaid() || $duplicate->isDropRaid());
        $keeper->setDropBurningSprings($keeper->isDropBurningSprings() || $duplicate->isDropBurningSprings());
        $keeper->setDropDailyOps($keeper->isDropDailyOps() || $duplicate->isDropDailyOps());
        $keeper->setDropBigfoot($keeper->isDropBigfoot() || $duplicate->isDropBigfoot());
        $keeper->setVendorRegs($keeper->isVendorRegs() || $duplicate->isVendorRegs());
        $keeper->setVendorSamuel($keeper->isVendorSamuel() || $duplicate->isVendorSamuel());
        $keeper->setVendorMortimer($keeper->isVendorMortimer() || $duplicate->isVendorMortimer());

        if (null === $keeper->getInfoHtml()) {
            $keeper->setInfoHtml($duplicate->getInfoHtml());
        }
        if (null === $keeper->getDropSourcesHtml()) {
            $keeper->setDropSourcesHtml($duplicate->getDropSourcesHtml());
        }
        if (null === $keeper->getRelationsHtml()) {
            $keeper->setRelationsHtml($duplicate->getRelationsHtml());
        }

        $this->persistence->mergeBookDuplicate($duplicate, $keeper);
    }

    /**
     * @param array<mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeImportRow(array $row): array
    {
        return $this->itemHydrator->normalizeRow($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function shouldIgnoreMissingSourceId(ItemImportFileContext $context, array $row): bool
    {
        if (!in_array($context->sourceProvider, ['fandom', 'fallout_wiki'], true)) {
            return false;
        }

        return isset($row['source_page'], $row['source_slug']);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, true>  $seenProviderRefs
     */
    private function shouldSkipDuplicateSourceRow(ItemImportFileContext $context, array $row, array $seenProviderRefs): bool
    {
        $providerRefKey = $this->buildProviderRefKey($context, $row);
        if (null === $providerRefKey) {
            return false;
        }

        return isset($seenProviderRefs[$providerRefKey]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildProviderRefKey(ItemImportFileContext $context, array $row): ?string
    {
        if (!in_array($context->sourceProvider, ['fandom', 'fallout_wiki'], true)) {
            return null;
        }

        $formId = $row['form_id'] ?? null;
        if (!is_scalar($formId) || '' === trim((string) $formId)) {
            return null;
        }

        return sprintf('%s:%s', $context->sourceProvider, strtoupper(trim((string) $formId)));
    }
}
