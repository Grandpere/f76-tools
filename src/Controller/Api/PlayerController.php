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

use App\Entity\UserEntity;
use App\Progression\Application\Player\Exception\PlayerNameConflictException;
use App\Progression\Application\Player\PlayerApplicationService;
use App\Progression\UI\Api\PlayerNameRequestExtractor;
use App\Progression\UI\Api\PlayerPayloadMapper;
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
        private readonly PlayerPayloadMapper $playerPayloadMapper,
        private readonly PlayerNameRequestExtractor $playerNameRequestExtractor,
    ) {
    }

    #[Route('', name: 'api_players_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $players = $this->playerApplicationService->listForUser($user);

        return $this->json($this->playerPayloadMapper->mapList($players));
    }

    #[Route('', name: 'api_players_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $name = $this->playerNameRequestExtractor->extract($request);
        if (null === $name) {
            return $this->json(['error' => 'Invalid player name.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $player = $this->playerApplicationService->createForUser($user, $name);
        } catch (PlayerNameConflictException) {
            return $this->json(['error' => 'Player name already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json($this->playerPayloadMapper->map($player), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->playerPayloadMapper->map($player));
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $name = $this->playerNameRequestExtractor->extract($request);
        if (null === $name) {
            return $this->json(['error' => 'Invalid player name.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $this->playerApplicationService->renameOwned($player, $name);
        } catch (PlayerNameConflictException) {
            return $this->json(['error' => 'Player name already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        return $this->json($this->playerPayloadMapper->map($player));
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
}
