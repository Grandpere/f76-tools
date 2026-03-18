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

namespace App\Catalog\Application\Item;

use App\Catalog\Application\Import\ItemSourceFieldMergeDecision;
use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Application\Import\ItemSourceMergeResult;
use App\Catalog\Domain\Entity\ItemEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookCatalogFrontApplicationService
{
    private const MERGE_PROVIDER_A = 'fandom';
    private const MERGE_PROVIDER_B = 'fallout_wiki';
    private const MERGE_STATUS_OPTIONS = ['aligned', 'generic_label', 'source_issue', 'material_conflict', 'no_merge'];
    private const CANONICAL_SIGNAL_FIELDS = [
        'purchase_currency',
        'containers',
        'random_encounters',
        'enemies',
        'events',
        'expeditions',
        'daily_ops',
        'raid',
        'unused_content',
        'seasonal_content',
        'treasure_maps',
        'quests',
        'vendors',
        'world_spawns',
    ];

    public function __construct(
        private readonly BookCatalogFrontReadRepository $bookCatalogFrontReadRepository,
        private readonly ItemSourceMergePolicy $itemSourceMergePolicy,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{
     *     rows:list<array{
     *         publicId:string,
     *         sourceId:int,
     *         name:string,
     *         description:?string,
     *         note:?string,
     *         rank:?int,
     *         price:?int,
     *         priceMinerva:?int,
     *         isNew:bool,
     *         bookListNumbers:list<int>,
     *         isSpecialList:bool,
     *         providers:list<string>,
     *         externalSources:list<array{provider:string,externalUrl:?string,externalRef:string}>,
     *         mergeLabel:?string,
     *         mergeStatus:string,
     *         mergeGenericLabelCount:int,
     *         mergeMaterialConflictCount:int,
     *         mergeSourceIssueCount:int,
     *         canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     *     }>,
     *     totalItems:int,
     *     totalPages:int,
     *     currentPage:int,
     *     mergeStatusOptions:list<string>,
     *     stats:array{
     *         aligned:int,
     *         generic_label:int,
     *         source_issue:int,
     *         material_conflict:int,
     *         no_merge:int
     *     }
     * }
     */
    public function browse(?string $query, ?string $mergeStatus, int $page, int $perPage): array
    {
        $items = $this->bookCatalogFrontReadRepository->findAllBooksDetailedOrdered();
        $rows = array_map($this->mapRow(...), $items);

        $normalizedQuery = $this->normalize($query);
        if ('' !== $normalizedQuery) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => str_contains($this->buildSearchHaystack($row), $normalizedQuery),
            ));
        }

        if (is_string($mergeStatus) && in_array($mergeStatus, self::MERGE_STATUS_OPTIONS, true)) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['mergeStatus'] === $mergeStatus,
            ));
        }

        /** @var array{aligned:int,generic_label:int,source_issue:int,material_conflict:int,no_merge:int} $stats */
        $stats = [
            'aligned' => 0,
            'generic_label' => 0,
            'source_issue' => 0,
            'material_conflict' => 0,
            'no_merge' => 0,
        ];

        foreach ($rows as $row) {
            ++$stats[$row['mergeStatus']];
        }

        $totalItems = count($rows);
        $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * max(1, $perPage);

        /** @var array{
         *     rows:list<array{
         *         publicId:string,
         *         sourceId:int,
         *         name:string,
         *         description:?string,
         *         note:?string,
         *         rank:?int,
         *         price:?int,
         *         priceMinerva:?int,
         *         isNew:bool,
         *         bookListNumbers:list<int>,
         *         isSpecialList:bool,
         *         providers:list<string>,
         *         externalSources:list<array{provider:string,externalUrl:?string,externalRef:string}>,
         *         mergeLabel:?string,
         *         mergeStatus:string,
         *         mergeGenericLabelCount:int,
         *         mergeMaterialConflictCount:int,
         *         mergeSourceIssueCount:int,
         *         canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
         *     }>,
         *     totalItems:int,
         *     totalPages:int,
         *     currentPage:int,
         *     mergeStatusOptions:list<string>,
         *     stats:array{aligned:int,generic_label:int,source_issue:int,material_conflict:int,no_merge:int}
         * } $result
         */
        $result = [
            'rows' => array_slice($rows, $offset, max(1, $perPage)),
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'mergeStatusOptions' => self::MERGE_STATUS_OPTIONS,
            'stats' => $stats,
        ];

        return $result;
    }

    /**
     * @return array{
     *     publicId:string,
     *     sourceId:int,
     *     name:string,
     *     description:?string,
     *     note:?string,
     *     rank:?int,
     *     price:?int,
     *     priceMinerva:?int,
     *     isNew:bool,
     *     bookListNumbers:list<int>,
     *     isSpecialList:bool,
     *     providers:list<string>,
     *     externalSources:list<array{provider:string,externalUrl:?string,externalRef:string}>,
     *     mergeLabel:?string,
     *     mergeStatus:string,
     *     mergeGenericLabelCount:int,
     *     mergeMaterialConflictCount:int,
     *     mergeSourceIssueCount:int,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * }
     */
    private function mapRow(ItemEntity $item): array
    {
        $externalSources = [];
        $providers = [];
        foreach ($item->getExternalSources() as $externalSource) {
            $providers[] = $externalSource->getProvider();
            $externalSources[] = [
                'provider' => $externalSource->getProvider(),
                'externalUrl' => $externalSource->getExternalUrl(),
                'externalRef' => $externalSource->getExternalRef(),
            ];
        }

        sort($providers);
        usort(
            $externalSources,
            static fn (array $left, array $right): int => [$left['provider'], $left['externalRef']] <=> [$right['provider'], $right['externalRef']],
        );

        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        $mergeSummary = $this->buildMergeSummary($mergeResult);

        $bookListNumbers = [];
        $isSpecialList = false;
        foreach ($item->getBookLists() as $bookList) {
            $bookListNumbers[] = $bookList->getListNumber();
            $isSpecialList = $isSpecialList || $bookList->isSpecialList();
        }
        sort($bookListNumbers);

        return [
            'publicId' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'description' => null !== $item->getDescKey() ? $this->translator->trans($item->getDescKey(), domain: 'items') : null,
            'note' => null !== $item->getNoteKey() ? $this->translator->trans($item->getNoteKey(), domain: 'items') : null,
            'rank' => $item->getRank(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'isNew' => $item->isNew(),
            'bookListNumbers' => array_values(array_unique($bookListNumbers)),
            'isSpecialList' => $isSpecialList,
            'providers' => array_values(array_unique($providers)),
            'externalSources' => $externalSources,
            'mergeLabel' => $mergeResult?->label,
            'mergeStatus' => $mergeSummary['status'],
            'mergeGenericLabelCount' => $mergeSummary['genericLabelCount'],
            'mergeMaterialConflictCount' => $mergeSummary['materialConflictCount'],
            'mergeSourceIssueCount' => $mergeSummary['sourceIssueCount'],
            'canonicalSignals' => null !== $mergeResult ? $this->extractCanonicalSignals($mergeResult) : [],
        ];
    }

    /**
     * @param array{
     *     publicId:string,
     *     sourceId:int,
     *     name:string,
     *     providers:list<string>,
     *     mergeLabel:?string,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * } $row
     */
    private function buildSearchHaystack(array $row): string
    {
        $parts = [
            $row['publicId'],
            (string) $row['sourceId'],
            $row['name'],
            (string) ($row['mergeLabel'] ?? ''),
            implode(' ', $row['providers']),
        ];

        foreach ($row['canonicalSignals'] as $signal) {
            $parts[] = $signal['label'];
            $parts[] = $signal['displayValue'];
            $parts[] = $signal['provider'];
        }

        return $this->normalize(implode(' ', $parts));
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * @return array{
     *     status:string,
     *     genericLabelCount:int,
     *     materialConflictCount:int,
     *     sourceIssueCount:int
     * }
     */
    private function buildMergeSummary(?ItemSourceMergeResult $result): array
    {
        if (null === $result) {
            return [
                'status' => 'no_merge',
                'genericLabelCount' => 0,
                'materialConflictCount' => 0,
                'sourceIssueCount' => 0,
            ];
        }

        $genericLabelCount = 0;
        $sourceIssueCount = 0;
        foreach ($result->decisions as $decision) {
            if (str_contains($decision->reason, 'generic_label')) {
                ++$genericLabelCount;
            }

            if (str_contains($decision->reason, 'internal_name_conflict')) {
                ++$sourceIssueCount;
            }
        }

        $materialConflictCount = count($result->conflicts);

        $status = 'aligned';
        if ($materialConflictCount > 0) {
            $status = 'material_conflict';
        } elseif ($sourceIssueCount > 0) {
            $status = 'source_issue';
        } elseif ($genericLabelCount > 0) {
            $status = 'generic_label';
        }

        return [
            'status' => $status,
            'genericLabelCount' => $genericLabelCount,
            'materialConflictCount' => $materialConflictCount,
            'sourceIssueCount' => $sourceIssueCount,
        ];
    }

    /**
     * @return list<array{field:string,label:string,displayValue:string,provider:string}>
     */
    private function extractCanonicalSignals(ItemSourceMergeResult $result): array
    {
        $signals = [];

        foreach ($result->decisions as $decision) {
            if (!in_array($decision->field, self::CANONICAL_SIGNAL_FIELDS, true)) {
                continue;
            }

            $displayValue = $this->normalizeSignalValue($decision);
            if (null === $displayValue) {
                continue;
            }

            $labelKey = 'catalog_books.signal_'.$decision->field;
            $signals[] = [
                'field' => $decision->field,
                'label' => $this->translator->trans($labelKey),
                'displayValue' => $displayValue,
                'provider' => $decision->provider,
            ];
        }

        return $signals;
    }

    private function normalizeSignalValue(ItemSourceFieldMergeDecision $decision): ?string
    {
        if ('purchase_currency' === $decision->field) {
            $currency = is_string($decision->value) ? trim($decision->value) : '';
            if ('' === $currency) {
                return null;
            }

            return $this->translator->trans('catalog_books.currency_'.$currency);
        }

        if (true === $decision->value) {
            return $this->translator->trans('catalog_books.signal_enabled');
        }

        return null;
    }
}
