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
     * @param list<string> $selectedLists
     * @param list<string> $selectedSignals
     *
     * @return array{
     *     rows:list<array{
     *         name:string,
     *         description:?string,
     *         note:?string,
     *         rank:?int,
     *         price:?int,
     *         priceMinerva:?int,
     *         priceCurrencyCode:string,
     *         priceCurrencyLabel:string,
     *         isNew:bool,
     *         bookListNumbers:list<int>,
     *         canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     *     }>,
     *     totalItems:int,
     *     totalPages:int,
     *     currentPage:int,
     *     listOptions:list<int>,
     *     signalOptions:list<string>
     * }
     */
    public function browse(?string $query, array $selectedLists, array $selectedSignals, int $page, int $perPage): array
    {
        $items = $this->bookCatalogFrontReadRepository->findAllBooksDetailedOrdered();
        $rows = array_map($this->mapRow(...), $items);
        $listOptions = $this->extractListOptions($rows);
        $signalOptions = $this->extractSignalOptions($rows);

        $normalizedQuery = $this->normalize($query);
        if ('' !== $normalizedQuery) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => str_contains($this->buildSearchHaystack($row), $normalizedQuery),
            ));
        }

        $selectedListNumbers = array_values(array_filter(
            array_map(
                static fn (string $value): ?int => ctype_digit($value) ? (int) $value : null,
                $selectedLists,
            ),
            static fn (?int $value): bool => null !== $value,
        ));

        if ([] !== $selectedListNumbers) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => [] !== array_intersect($selectedListNumbers, $row['bookListNumbers']),
            ));
        }

        $normalizedSignals = array_filter(
            array_map(
                fn (string $value): string => $this->normalize($value),
                $selectedSignals,
            ),
            static fn (string $value): bool => '' !== $value,
        );

        if ([] !== $normalizedSignals) {
            $rows = array_values(array_filter(
                $rows,
                static function (array $row) use ($normalizedSignals): bool {
                    $rowSignals = array_map(
                        static fn (array $signal): string => $signal['field'],
                        $row['canonicalSignals'],
                    );

                    return [] !== array_intersect($normalizedSignals, $rowSignals);
                },
            ));
        }

        $totalItems = count($rows);
        $totalPages = max(1, (int) ceil($totalItems / max(1, $perPage)));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * max(1, $perPage);

        return [
            'rows' => array_slice($rows, $offset, max(1, $perPage)),
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $currentPage,
            'listOptions' => $listOptions,
            'signalOptions' => $signalOptions,
        ];
    }

    /**
     * @return array{
     *     name:string,
     *     description:?string,
     *     note:?string,
     *     rank:?int,
     *     price:?int,
     *     priceMinerva:?int,
     *     priceCurrencyCode:string,
     *     priceCurrencyLabel:string,
     *     isNew:bool,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * }
     */
    private function mapRow(ItemEntity $item): array
    {
        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        $canonicalSignals = null !== $mergeResult ? $this->extractCanonicalSignals($mergeResult) : [];

        $bookListNumbers = [];
        foreach ($item->getBookLists() as $bookList) {
            $bookListNumbers[] = $bookList->getListNumber();
        }
        sort($bookListNumbers);

        $priceCurrencyCode = $this->extractPurchaseCurrencyCode($mergeResult);
        $priceCurrencyLabel = $this->translator->trans('catalog_books.currency_'.$priceCurrencyCode);

        return [
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'description' => null !== $item->getDescKey() ? $this->translator->trans($item->getDescKey(), domain: 'items') : null,
            'note' => null !== $item->getNoteKey() ? $this->translator->trans($item->getNoteKey(), domain: 'items') : null,
            'rank' => $item->getRank(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'priceCurrencyCode' => $priceCurrencyCode,
            'priceCurrencyLabel' => $priceCurrencyLabel,
            'isNew' => $item->isNew(),
            'bookListNumbers' => array_values(array_unique($bookListNumbers)),
            'canonicalSignals' => array_values(array_filter(
                $canonicalSignals,
                static fn (array $signal): bool => 'purchase_currency' !== $signal['field'],
            )),
        ];
    }

    /**
     * @param array{
     *     name:string,
     *     description:?string,
     *     note:?string,
     *     bookListNumbers:list<int>,
     *     canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>
     * } $row
     */
    private function buildSearchHaystack(array $row): string
    {
        $parts = [
            $row['name'],
            (string) ($row['description'] ?? ''),
            (string) ($row['note'] ?? ''),
            implode(' ', array_map(static fn (int $list): string => (string) $list, $row['bookListNumbers'])),
        ];

        foreach ($row['canonicalSignals'] as $signal) {
            $parts[] = $signal['label'];
            $parts[] = $signal['displayValue'];
        }

        return $this->normalize(implode(' ', $parts));
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
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

            $signals[] = [
                'field' => $decision->field,
                'label' => $this->translator->trans('catalog_books.signal_'.$decision->field),
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

    private function extractPurchaseCurrencyCode(?ItemSourceMergeResult $result): string
    {
        if (null === $result) {
            return 'caps';
        }

        foreach ($result->decisions as $decision) {
            if ('purchase_currency' !== $decision->field || !is_string($decision->value)) {
                continue;
            }

            $currency = trim($decision->value);
            if ('' !== $currency) {
                return $currency;
            }
        }

        return 'caps';
    }

    /**
     * @param list<array{bookListNumbers:list<int>}> $rows
     *
     * @return list<int>
     */
    private function extractListOptions(array $rows): array
    {
        $listOptions = [];
        foreach ($rows as $row) {
            foreach ($row['bookListNumbers'] as $listNumber) {
                $listOptions[$listNumber] = true;
            }
        }

        $numbers = array_map('intval', array_keys($listOptions));
        sort($numbers);

        return $numbers;
    }

    /**
     * @param list<array{canonicalSignals:list<array{field:string,label:string,displayValue:string,provider:string}>}> $rows
     *
     * @return list<string>
     */
    private function extractSignalOptions(array $rows): array
    {
        $signalOptions = [];
        foreach ($rows as $row) {
            foreach ($row['canonicalSignals'] as $signal) {
                $signalOptions[$signal['field']] = true;
            }
        }

        return array_keys($signalOptions);
    }
}
