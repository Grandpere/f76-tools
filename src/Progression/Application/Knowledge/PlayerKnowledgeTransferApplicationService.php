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

namespace App\Progression\Application\Knowledge;

use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Progression\Domain\Entity\PlayerEntity;
use App\Progression\Domain\Entity\PlayerItemKnowledgeEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class PlayerKnowledgeTransferApplicationService
{
    private const IMPORT_VERSION = 1;

    public function __construct(
        private readonly PlayerKnowledgeTransferRepository $knowledgeRepository,
        private readonly ItemKnowledgeTransferRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     version: int,
     *     playerId: string,
     *     exportedAt: string,
     *     learnedItems: list<array{type: string, sourceId: int}>
     * }
     */
    public function export(PlayerEntity $player): array
    {
        $learnedItems = $this->knowledgeRepository->findLearnedItemsByPlayer($player);
        $payload = [];
        foreach ($learnedItems as $item) {
            $payload[] = [
                'type' => $item->getType()->value,
                'sourceId' => $item->getSourceId(),
            ];
        }

        return [
            'version' => self::IMPORT_VERSION,
            'playerId' => $player->getPublicId(),
            'exportedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            'learnedItems' => $payload,
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{ok: false, error: string}|array{
     *     ok: true,
     *     replace: bool,
     *     unknownItems: list<array{type: string, sourceId: int}>,
     *     wouldAdd: int,
     *     wouldRemove: int
     * }
     */
    public function previewImport(PlayerEntity $player, array $body): array
    {
        $plan = $this->buildImportPlan($player, $body);
        if (!$plan['ok']) {
            return $plan;
        }

        return [
            'ok' => true,
            'replace' => $plan['replace'],
            'wouldAdd' => count($plan['toAdd']),
            'wouldRemove' => count($plan['toRemove']),
            'unknownItems' => $plan['unknownItems'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{ok: false, error: string, unknownItems?: list<array{type: string, sourceId: int}>}|array{
     *     ok: true,
     *     replace: bool,
     *     added: int,
     *     removed: int,
     *     learnedTotal: int
     * }
     */
    public function import(PlayerEntity $player, array $body): array
    {
        $plan = $this->buildImportPlan($player, $body);
        if (!$plan['ok']) {
            return $plan;
        }

        if ([] !== $plan['unknownItems']) {
            return [
                'ok' => false,
                'error' => 'Unknown items in payload.',
                'unknownItems' => $plan['unknownItems'],
            ];
        }

        if ([] !== $plan['toRemove']) {
            $this->knowledgeRepository->deleteByPlayerAndItemIds($player, $plan['toRemove']);
        }

        if ([] !== $plan['toAdd']) {
            $itemsToAdd = $this->itemRepository->findByIds($plan['toAdd']);
            foreach ($itemsToAdd as $item) {
                $this->entityManager->persist(new PlayerItemKnowledgeEntity()
                    ->setPlayer($player)
                    ->setItem($item)
                    ->setLearnedAt(new DateTimeImmutable()));
            }
            $this->entityManager->flush();
        }

        return [
            'ok' => true,
            'replace' => $plan['replace'],
            'added' => count($plan['toAdd']),
            'removed' => count($plan['toRemove']),
            'learnedTotal' => $this->knowledgeRepository->countLearnedByPlayer($player),
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{ok: false, error: string}|array{
     *     ok: true,
     *     replace: bool,
     *     toAdd: list<int>,
     *     toRemove: list<int>,
     *     unknownItems: list<array{type: string, sourceId: int}>
     * }
     */
    private function buildImportPlan(PlayerEntity $player, array $body): array
    {
        $versionError = $this->validateVersion($body);
        if (null !== $versionError) {
            return ['ok' => false, 'error' => $versionError];
        }

        $replace = $this->readReplaceFlag($body);
        $normalized = $this->normalizeTargetsWithValidation($body['learnedItems'] ?? null);
        if (!$normalized['ok']) {
            return ['ok' => false, 'error' => $normalized['error']];
        }

        $resolved = $this->resolveTargets($normalized['targets']);
        $currentItemIds = $this->knowledgeRepository->findLearnedItemIdsByPlayer($player);

        $currentMap = array_fill_keys(array_map('intval', $currentItemIds), true);
        $targetMap = array_fill_keys(array_map('intval', $resolved['itemIds']), true);

        $toAdd = array_values(array_diff(array_keys($targetMap), array_keys($currentMap)));
        $toRemove = $replace
            ? array_values(array_diff(array_keys($currentMap), array_keys($targetMap)))
            : [];

        return [
            'ok' => true,
            'replace' => $replace,
            'toAdd' => $toAdd,
            'toRemove' => $toRemove,
            'unknownItems' => $resolved['unknownItems'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function readReplaceFlag(array $body): bool
    {
        $raw = $body['replace'] ?? true;
        if (is_bool($raw)) {
            return $raw;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function validateVersion(array $body): ?string
    {
        $raw = $body['version'] ?? self::IMPORT_VERSION;
        if (!is_int($raw) && !is_numeric($raw)) {
            return 'Invalid version.';
        }
        if (self::IMPORT_VERSION !== (int) $raw) {
            return sprintf('Unsupported version. Expected %d.', self::IMPORT_VERSION);
        }

        return null;
    }

    /**
     * @return array{ok: bool, error: string, targets: array<string, list<int>>}
     */
    private function normalizeTargetsWithValidation(mixed $raw): array
    {
        if (!is_array($raw)) {
            return ['ok' => false, 'error' => 'Invalid learnedItems list.', 'targets' => []];
        }

        if (count($raw) > 10000) {
            return ['ok' => false, 'error' => 'learnedItems exceeds maximum size (10000).', 'targets' => []];
        }

        $byType = [];
        foreach ($raw as $index => $row) {
            if (!is_array($row)) {
                return ['ok' => false, 'error' => sprintf('Invalid row at index %d.', (int) $index), 'targets' => []];
            }
            $typeRaw = $row['type'] ?? null;
            $sourceIdRaw = $row['sourceId'] ?? null;
            if (!is_string($typeRaw) || (!is_int($sourceIdRaw) && !is_numeric($sourceIdRaw))) {
                return ['ok' => false, 'error' => sprintf('Invalid row format at index %d.', (int) $index), 'targets' => []];
            }
            $type = ItemTypeEnum::tryFrom(strtoupper(trim($typeRaw)));
            if (!$type instanceof ItemTypeEnum) {
                return ['ok' => false, 'error' => sprintf('Invalid type at index %d.', (int) $index), 'targets' => []];
            }

            $sourceId = (int) $sourceIdRaw;
            if ($sourceId <= 0) {
                return ['ok' => false, 'error' => sprintf('Invalid sourceId at index %d.', (int) $index), 'targets' => []];
            }

            $typeKey = $type->value;
            if (!isset($byType[$typeKey])) {
                $byType[$typeKey] = [];
            }
            $byType[$typeKey][] = $sourceId;
        }

        foreach ($byType as $typeKey => $sourceIds) {
            $byType[$typeKey] = array_values(array_unique($sourceIds));
        }

        return ['ok' => true, 'error' => '', 'targets' => $byType];
    }

    /**
     * @param array<string, list<int>> $targets
     *
     * @return array{itemIds: list<int>, unknownItems: list<array{type: string, sourceId: int}>}
     */
    private function resolveTargets(array $targets): array
    {
        $ids = [];
        $unknown = [];
        foreach ($targets as $typeRaw => $sourceIds) {
            $type = ItemTypeEnum::tryFrom($typeRaw);
            if (!$type instanceof ItemTypeEnum) {
                continue;
            }

            $items = $this->itemRepository->findByTypeAndSourceIds($type, $sourceIds);
            $foundSourceIds = [];
            foreach ($items as $item) {
                $id = $item->getId();
                if (null !== $id) {
                    $ids[] = $id;
                }
                $foundSourceIds[] = $item->getSourceId();
            }

            $missing = array_values(array_diff($sourceIds, $foundSourceIds));
            foreach ($missing as $sourceId) {
                $unknown[] = [
                    'type' => $type->value,
                    'sourceId' => $sourceId,
                ];
            }
        }

        return [
            'itemIds' => array_values(array_unique($ids)),
            'unknownItems' => $unknown,
        ];
    }
}
