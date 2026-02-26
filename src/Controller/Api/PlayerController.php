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

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Player\Exception\PlayerNameConflictException;
use App\Progression\Application\Player\PlayerApplicationService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players')]
final class PlayerController extends AbstractController
{
    public function __construct(
        private readonly PlayerApplicationService $playerApplicationService,
    ) {
    }

    #[Route('', name: 'api_players_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $players = $this->playerApplicationService->listForUser($user);

        $payload = array_map(static fn (PlayerEntity $player): array => [
            'id' => $player->getPublicId(),
            'name' => $player->getName(),
            'createdAt' => $player->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $player->getUpdatedAt()->format(DATE_ATOM),
        ], $players);

        return $this->json($payload);
    }

    #[Route('', name: 'api_players_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $data = $this->decodeJson($request);

        $name = $this->normalizeName($data['name'] ?? null);
        if (null === $name) {
            return $this->json(['error' => 'Invalid player name.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $player = $this->playerApplicationService->createForUser($user, $name);
        } catch (PlayerNameConflictException) {
            return $this->json(['error' => 'Player name already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $player->getPublicId(),
            'name' => $player->getName(),
            'createdAt' => $player->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $player->getUpdatedAt()->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $player->getPublicId(),
            'name' => $player->getName(),
            'createdAt' => $player->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $player->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = $this->decodeJson($request);
        $name = $this->normalizeName($data['name'] ?? null);
        if (null === $name) {
            return $this->json(['error' => 'Invalid player name.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $this->playerApplicationService->renameOwned($player, $name);
        } catch (PlayerNameConflictException) {
            return $this->json(['error' => 'Player name already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json([
            'id' => $player->getPublicId(),
            'name' => $player->getName(),
            'createdAt' => $player->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $player->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->playerApplicationService->delete($player);

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

    private function normalizeName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
