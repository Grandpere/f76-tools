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

namespace App\Controller\Api;

use App\Domain\Item\ItemTypeEnum;
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use App\Entity\UserEntity;
use App\Repository\ItemEntityRepository;
use App\Repository\PlayerItemKnowledgeEntityRepository;
use App\Progression\Application\Knowledge\PlayerKnowledgeApplicationService;
use App\Service\PlayerItemKnowledgeManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/knowledge')]
final class PlayerKnowledgeTransferController extends AbstractController
{
    private const IMPORT_VERSION = 1;

    public function __construct(
        private readonly PlayerKnowledgeApplicationService $playerKnowledgeApplicationService,
        private readonly PlayerItemKnowledgeEntityRepository $knowledgeRepository,
        private readonly ItemEntityRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/export', name: 'api_player_knowledge_export', methods: ['GET'])]
    public function export(string $playerId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $learnedItems = $this->knowledgeRepository->findLearnedItemsByPlayer($player);
        $payload = [];
        foreach ($learnedItems as $item) {
            $payload[] = [
                'type' => $item->getType()->value,
                'sourceId' => $item->getSourceId(),
            ];
        }

        return $this->json([
            'version' => 1,
            'playerId' => $player->getPublicId(),
            'exportedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'learnedItems' => $payload,
        ]);
    }

    #[Route('/import', name: 'api_player_knowledge_import', methods: ['POST'])]
    public function import(string $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $body = $this->decodeJson($request);
        $versionError = $this->validateVersion($body);
        if (null !== $versionError) {
            return $this->json(['error' => $versionError], JsonResponse::HTTP_BAD_REQUEST);
        }
        $replace = $this->readReplaceFlag($body);
        $normalized = $this->normalizeTargetsWithValidation($body['learnedItems'] ?? null);
        if (!$normalized['ok']) {
            return $this->json(['error' => $normalized['error']], JsonResponse::HTTP_BAD_REQUEST);
        }
        $targets = $normalized['targets'];

        $resolved = $this->resolveTargets($targets);
        $targetItemIds = $resolved['itemIds'];
        $unknownItems = $resolved['unknownItems'];

        if ([] !== $unknownItems) {
            return $this->json([
                'error' => 'Unknown items in payload.',
                'unknownItems' => $unknownItems,
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $currentItemIds = $this->knowledgeRepository->findLearnedItemIdsByPlayer($player);
        $currentMap = array_fill_keys(array_map('intval', $currentItemIds), true);
        $targetMap = array_fill_keys(array_map('intval', $targetItemIds), true);

        $toAdd = array_values(array_diff(array_keys($targetMap), array_keys($currentMap)));
        $toRemove = $replace
            ? array_values(array_diff(array_keys($currentMap), array_keys($targetMap)))
            : [];

        if ([] !== $toRemove) {
            $this->entityManager->createQueryBuilder()
                ->delete(PlayerItemKnowledgeEntity::class, 'k')
                ->andWhere('k.player = :player')
                ->andWhere('IDENTITY(k.item) IN (:itemIds)')
                ->setParameter('player', $player)
                ->setParameter('itemIds', $toRemove)
                ->getQuery()
                ->execute();
        }

        if ([] !== $toAdd) {
            $itemsToAdd = $this->itemRepository->findBy(['id' => $toAdd]);
            foreach ($itemsToAdd as $item) {
                $this->entityManager->persist((new PlayerItemKnowledgeEntity())
                    ->setPlayer($player)
                    ->setItem($item)
                    ->setLearnedAt(new DateTimeImmutable()));
            }
            $this->entityManager->flush();
        }

        $updatedLearnedCount = $this->knowledgeRepository->countLearnedByPlayer($player);

        return $this->json([
            'ok' => true,
            'replace' => $replace,
            'added' => count($toAdd),
            'removed' => count($toRemove),
            'learnedTotal' => $updatedLearnedCount,
        ]);
    }

    #[Route('/preview-import', name: 'api_player_knowledge_preview_import', methods: ['POST'])]
    public function previewImport(string $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $body = $this->decodeJson($request);
        $versionError = $this->validateVersion($body);
        if (null !== $versionError) {
            return $this->json(['error' => $versionError], JsonResponse::HTTP_BAD_REQUEST);
        }
        $replace = $this->readReplaceFlag($body);
        $normalized = $this->normalizeTargetsWithValidation($body['learnedItems'] ?? null);
        if (!$normalized['ok']) {
            return $this->json(['error' => $normalized['error']], JsonResponse::HTTP_BAD_REQUEST);
        }
        $targets = $normalized['targets'];

        $resolved = $this->resolveTargets($targets);
        $targetItemIds = $resolved['itemIds'];
        $unknownItems = $resolved['unknownItems'];

        $currentItemIds = $this->knowledgeRepository->findLearnedItemIdsByPlayer($player);
        $currentMap = array_fill_keys(array_map('intval', $currentItemIds), true);
        $targetMap = array_fill_keys(array_map('intval', $targetItemIds), true);

        $toAdd = array_values(array_diff(array_keys($targetMap), array_keys($currentMap)));
        $toRemove = $replace
            ? array_values(array_diff(array_keys($currentMap), array_keys($targetMap)))
            : [];

        return $this->json([
            'ok' => true,
            'replace' => $replace,
            'wouldAdd' => count($toAdd),
            'wouldRemove' => count($toRemove),
            'unknownItems' => $unknownItems,
        ]);
    }

    private function resolveOwnedPlayer(string $playerId): ?PlayerEntity
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $this->playerKnowledgeApplicationService->resolveOwnedPlayer($user, $playerId);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($payload)) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
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
     *
     * @return string|null
     */
    private function validateVersion(array $body): ?string
    {
        $raw = $body['version'] ?? self::IMPORT_VERSION;
        if (!is_int($raw) && !is_numeric($raw)) {
            return 'Invalid version.';
        }
        if ((int) $raw !== self::IMPORT_VERSION) {
            return sprintf('Unsupported version. Expected %d.', self::IMPORT_VERSION);
        }

        return null;
    }

    /**
     * @param mixed $raw
     *
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
