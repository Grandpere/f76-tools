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

namespace App\Support\UI\Admin\Controller;

use App\Catalog\Application\Admin\AdminCatalogItemReadRepository;
use App\Catalog\Application\Import\ItemSourceMergePolicy;
use App\Catalog\Domain\Entity\ItemBookListEntity;
use App\Catalog\Domain\Entity\ItemEntity;
use App\Catalog\Domain\Entity\ItemExternalSourceEntity;
use App\Catalog\Domain\Item\ItemTypeEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale<en|fr|de>}/admin/catalog/items', defaults: ['_locale' => 'en'])]
final class CatalogItemController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminInputSanitizerTrait;

    private const MERGE_PROVIDER_A = 'fandom';
    private const MERGE_PROVIDER_B = 'fallout_wiki';

    public function __construct(
        private readonly AdminCatalogItemReadRepository $adminCatalogItemReadRepository,
        private readonly ItemSourceMergePolicy $itemSourceMergePolicy,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_catalog_items', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->ensureAdminAccess();

        $type = $this->optionalItemType($request->query->get('type'));
        $query = $this->normalizeQuery($this->optionalString($request->query->get('q')));
        $perPage = $this->sanitizePositiveInt($request->query->get('perPage'), 20, 100);
        $totalItems = $this->adminCatalogItemReadRepository->countByAdminQuery($type, $query);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($this->sanitizePositiveInt($request->query->get('page'), 1), $totalPages);

        $items = $this->adminCatalogItemReadRepository->findByAdminQuery($type, $query, $page, $perPage);
        $selectedPublicId = $this->optionalString($request->query->get('item'));
        if ((null === $selectedPublicId || '' === trim($selectedPublicId)) && [] !== $items) {
            $selectedPublicId = $items[0]->getPublicId();
        }

        $selectedItem = null;
        if (is_string($selectedPublicId) && '' !== trim($selectedPublicId)) {
            $selectedItem = $this->adminCatalogItemReadRepository->findOneDetailedByPublicId($selectedPublicId);
        }

        return $this->render('admin/catalog_items.html.twig', [
            'type' => $type?->value,
            'typeOptions' => ItemTypeEnum::cases(),
            'query' => $query ?? '',
            'items' => array_map($this->mapListRow(...), $items),
            'selectedItem' => $selectedItem instanceof ItemEntity ? $this->mapSelectedItem($selectedItem) : null,
            'selectedPublicId' => $selectedItem?->getPublicId(),
            'totalItems' => $totalItems,
            'pageRows' => count($items),
            'perPage' => $perPage,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * @return array{
     *     publicId:string,
     *     sourceId:int,
     *     type:string,
     *     name:string,
     *     providerSummary:string,
     *     mergeStatus:string,
     *     mergeGenericLabelCount:int,
     *     mergeMaterialConflictCount:int,
     *     mergeSourceIssueCount:int
     * }
     */
    private function mapListRow(ItemEntity $item): array
    {
        $providers = [];
        foreach ($item->getExternalSources() as $externalSource) {
            $providers[] = $externalSource->getProvider();
        }

        sort($providers);
        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);
        $mergeSummary = $this->buildMergeSummary($mergeResult);

        return [
            'publicId' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'providerSummary' => implode(', ', array_values(array_unique($providers))),
            'mergeStatus' => $mergeSummary['status'],
            'mergeGenericLabelCount' => $mergeSummary['genericLabelCount'],
            'mergeMaterialConflictCount' => $mergeSummary['materialConflictCount'],
            'mergeSourceIssueCount' => $mergeSummary['sourceIssueCount'],
        ];
    }

    /**
     * @return array{
     *     publicId:string,
     *     sourceId:int,
     *     type:string,
     *     name:string,
     *     nameKey:string,
     *     description:?string,
     *     descKey:?string,
     *     note:?string,
     *     noteKey:?string,
     *     rank:?int,
     *     price:?int,
     *     priceMinerva:?int,
     *     isNew:bool,
     *     flags: array<string, bool>,
     *     bookLists:list<array{listNumber:int,isSpecial:bool}>,
     *     externalSources:list<array{
     *         provider:string,
     *         externalRef:string,
     *         externalUrl:?string,
     *         metadata:array<string,mixed>,
     *         metadataJson:string
     *     }>,
     *     sourceMerge:?array{
     *         label:string,
     *         summary:array{
     *             status:string,
     *             retainedFields:int,
     *             genericLabelCount:int,
     *             materialConflictCount:int,
     *             sourceIssueCount:int
     *         },
     *         decisions:list<array{
     *             field:string,
     *             provider:string,
     *             value:mixed,
     *             reason:string,
     *             other_value:mixed
     *         }>,
     *         conflicts:list<array{
     *             field:string,
     *             value_a:mixed,
     *             value_b:mixed,
     *             reason:string
     *         }>
     *     }
     * }
     */
    private function mapSelectedItem(ItemEntity $item): array
    {
        $bookLists = array_map(
            static fn (ItemBookListEntity $bookList): array => [
                'listNumber' => $bookList->getListNumber(),
                'isSpecial' => $bookList->isSpecialList(),
            ],
            $item->getBookLists()->toArray(),
        );
        usort($bookLists, static fn (array $left, array $right): int => $left['listNumber'] <=> $right['listNumber']);

        $externalSources = array_map(
            fn (ItemExternalSourceEntity $externalSource): array => $this->mapExternalSource($externalSource),
            $item->getExternalSources()->toArray(),
        );
        usort(
            $externalSources,
            static fn (array $left, array $right): int => [$left['provider'], $left['externalRef']] <=> [$right['provider'], $right['externalRef']],
        );

        $mergeResult = $this->itemSourceMergePolicy->merge($item, self::MERGE_PROVIDER_A, self::MERGE_PROVIDER_B);

        return [
            'publicId' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'nameKey' => $item->getNameKey(),
            'description' => null !== $item->getDescKey() ? $this->translator->trans($item->getDescKey(), domain: 'items') : null,
            'descKey' => $item->getDescKey(),
            'note' => null !== $item->getNoteKey() ? $this->translator->trans($item->getNoteKey(), domain: 'items') : null,
            'noteKey' => $item->getNoteKey(),
            'rank' => $item->getRank(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'isNew' => $item->isNew(),
            'flags' => [
                'dropRaid' => $item->isDropRaid(),
                'dropBurningSprings' => $item->isDropBurningSprings(),
                'dropDailyOps' => $item->isDropDailyOps(),
                'dropBigfoot' => $item->isDropBigfoot(),
                'vendorRegs' => $item->isVendorRegs(),
                'vendorSamuel' => $item->isVendorSamuel(),
                'vendorMortimer' => $item->isVendorMortimer(),
            ],
            'bookLists' => $bookLists,
            'externalSources' => $externalSources,
            'sourceMerge' => null !== $mergeResult ? $this->mapMergeResult($mergeResult) : null,
        ];
    }

    /**
     * @return array{
     *     provider:string,
     *     externalRef:string,
     *     externalUrl:?string,
     *     metadata:array<string,mixed>,
     *     metadataJson:string
     * }
     */
    private function mapExternalSource(ItemExternalSourceEntity $externalSource): array
    {
        $metadata = $externalSource->getMetadata() ?? [];
        ksort($metadata);

        return [
            'provider' => $externalSource->getProvider(),
            'externalRef' => $externalSource->getExternalRef(),
            'externalUrl' => $externalSource->getExternalUrl(),
            'metadata' => $metadata,
            'metadataJson' => json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array{
     *     label:string,
     *     summary:array{
     *         status:string,
     *         retainedFields:int,
     *         genericLabelCount:int,
     *         materialConflictCount:int,
     *         sourceIssueCount:int
     *     },
     *     decisions:list<array{
     *         field:string,
     *         provider:string,
     *         value:mixed,
     *         reason:string,
     *         other_value:mixed
     *     }>,
     *     conflicts:list<array{
     *         field:string,
     *         value_a:mixed,
     *         value_b:mixed,
     *         reason:string
     *     }>
     * }
     */
    private function mapMergeResult(\App\Catalog\Application\Import\ItemSourceMergeResult $result): array
    {
        return [
            'label' => $result->label,
            'summary' => $this->buildMergeSummary($result),
            'decisions' => array_map(
                static fn (\App\Catalog\Application\Import\ItemSourceFieldMergeDecision $decision): array => [
                    'field' => $decision->field,
                    'provider' => $decision->provider,
                    'value' => $decision->value,
                    'reason' => $decision->reason,
                    'other_value' => $decision->otherValue,
                ],
                $result->decisions,
            ),
            'conflicts' => array_map(
                static fn (\App\Catalog\Application\Import\ItemSourceMergeConflict $conflict): array => [
                    'field' => $conflict->field,
                    'value_a' => $conflict->valueA,
                    'value_b' => $conflict->valueB,
                    'reason' => $conflict->reason,
                ],
                $result->conflicts,
            ),
        ];
    }

    /**
     * @return array{
     *     status:string,
     *     retainedFields:int,
     *     genericLabelCount:int,
     *     materialConflictCount:int,
     *     sourceIssueCount:int
     * }
     */
    private function buildMergeSummary(?\App\Catalog\Application\Import\ItemSourceMergeResult $result): array
    {
        if (null === $result) {
            return [
                'status' => 'no_merge',
                'retainedFields' => 0,
                'genericLabelCount' => 0,
                'materialConflictCount' => 0,
                'sourceIssueCount' => 0,
            ];
        }

        $genericLabelCount = 0;
        $sourceIssueCount = 0;
        foreach ($result->decisions as $decision) {
            if ('generic_label_confirmed_by_specific_target' === $decision->reason) {
                ++$genericLabelCount;
            }

            if ('preferred_other_source_internal_name_conflict' === $decision->reason) {
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
            'retainedFields' => count($result->decisions),
            'genericLabelCount' => $genericLabelCount,
            'materialConflictCount' => $materialConflictCount,
            'sourceIssueCount' => $sourceIssueCount,
        ];
    }

    private function optionalItemType(mixed $value): ?ItemTypeEnum
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        return ItemTypeEnum::tryFrom(strtoupper($normalized));
    }

    private function normalizeQuery(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' !== $normalized ? $normalized : null;
    }
}
