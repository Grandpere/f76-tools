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
use App\Service\PlayerItemKnowledgeManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players/{playerId<\d+>}/knowledge')]
final class PlayerKnowledgeTransferController extends AbstractController
{
    public function __construct(
        private readonly PlayerItemKnowledgeManager $knowledgeManager,
        private readonly PlayerItemKnowledgeEntityRepository $knowledgeRepository,
        private readonly ItemEntityRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/export', name: 'api_player_knowledge_export', methods: ['GET'])]
    public function export(int $playerId): JsonResponse
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
            'playerId' => $player->getId(),
            'exportedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'learnedItems' => $payload,
        ]);
    }

    #[Route('/import', name: 'api_player_knowledge_import', methods: ['POST'])]
    public function import(int $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $body = $this->decodeJson($request);
        $replace = $this->readReplaceFlag($body);
        $targets = $this->normalizeTargets($body['learnedItems'] ?? null);
        if (false === $targets) {
            return $this->json(['error' => 'Invalid payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $targetItemIds = $this->resolveTargetItemIds($targets);
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

    private function resolveOwnedPlayer(int $playerId): ?PlayerEntity
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $this->knowledgeManager->resolveOwnedPlayer($playerId, $user);
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
     * @param mixed $raw
     *
     * @return array<string, list<int>>|false
     */
    private function normalizeTargets(mixed $raw): array|false
    {
        if (!is_array($raw)) {
            return false;
        }

        $byType = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                return false;
            }
            $typeRaw = $row['type'] ?? null;
            $sourceIdRaw = $row['sourceId'] ?? null;
            if (!is_string($typeRaw) || (!is_int($sourceIdRaw) && !is_numeric($sourceIdRaw))) {
                return false;
            }
            $type = ItemTypeEnum::tryFrom(strtoupper(trim($typeRaw)));
            if (!$type instanceof ItemTypeEnum) {
                return false;
            }

            $typeKey = $type->value;
            if (!isset($byType[$typeKey])) {
                $byType[$typeKey] = [];
            }
            $byType[$typeKey][] = (int) $sourceIdRaw;
        }

        foreach ($byType as $typeKey => $sourceIds) {
            $byType[$typeKey] = array_values(array_unique($sourceIds));
        }

        return $byType;
    }

    /**
     * @param array<string, list<int>> $targets
     *
     * @return list<int>
     */
    private function resolveTargetItemIds(array $targets): array
    {
        $ids = [];
        foreach ($targets as $typeRaw => $sourceIds) {
            $type = ItemTypeEnum::tryFrom($typeRaw);
            if (!$type instanceof ItemTypeEnum) {
                continue;
            }

            $items = $this->itemRepository->findByTypeAndSourceIds($type, $sourceIds);
            foreach ($items as $item) {
                $id = $item->getId();
                if (null !== $id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
