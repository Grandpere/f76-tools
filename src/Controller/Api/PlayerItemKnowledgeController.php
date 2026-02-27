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
use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeApplicationService;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/items')]
final class PlayerItemKnowledgeController extends AbstractController
{
    public function __construct(
        private readonly PlayerKnowledgeCatalogApplicationService $playerKnowledgeCatalogApplicationService,
        private readonly PlayerKnowledgeApplicationService $playerKnowledgeApplicationService,
        private readonly PlayerKnowledgeItemPayloadMapper $playerKnowledgeItemPayloadMapper,
    ) {
    }

    #[Route('', name: 'api_player_items_index', methods: ['GET'])]
    public function index(string $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $type = $this->parseType($request->query->get('type'));
        if (false === $type) {
            return $this->json(['error' => 'Invalid item type.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $query = $this->normalizeSearchQuery($request->query->get('q'));
        $catalogRows = $this->playerKnowledgeCatalogApplicationService->listForPlayer($player, $type);

        $payload = [];
        foreach ($catalogRows as $row) {
            $payload[] = $this->playerKnowledgeItemPayloadMapper->map($row['item'], $row['learned']);
        }
        if (null !== $query) {
            $payload = array_values(array_filter(
                $payload,
                static function (array $row) use ($query): bool {
                    $name = mb_strtolower($row['name']);
                    $description = mb_strtolower((string) ($row['description'] ?? ''));
                    $nameKey = mb_strtolower($row['nameKey']);
                    $descKey = mb_strtolower((string) ($row['descKey'] ?? ''));

                    return str_contains($name, $query)
                        || str_contains($description, $query)
                        || str_contains($nameKey, $query)
                        || str_contains($descKey, $query);
                },
            ));
        }

        return $this->json($payload);
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_set', methods: ['PUT'])]
    public function setLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->playerKnowledgeApplicationService->resolveItemByPublicId($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->playerKnowledgeApplicationService->markLearned($player, $item);

        return $this->json($this->playerKnowledgeItemPayloadMapper->map($item, true));
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_unset', methods: ['DELETE'])]
    public function unsetLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->playerKnowledgeApplicationService->resolveItemByPublicId($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->playerKnowledgeApplicationService->unmarkLearned($player, $item);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function getAuthenticatedUser(): UserEntity
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $user;
    }

    private function resolveOwnedPlayer(string $playerId): ?PlayerEntity
    {
        $user = $this->getAuthenticatedUser();

        return $this->playerKnowledgeApplicationService->resolveOwnedPlayer($user, $playerId);
    }

    private function parseType(mixed $value): ItemTypeEnum|false|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_string($value)) {
            return false;
        }

        return ItemTypeEnum::tryFrom(strtoupper(trim($value))) ?? false;
    }

    private function normalizeSearchQuery(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }

}
