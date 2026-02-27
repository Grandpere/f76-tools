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
use App\Progression\Application\Player\PlayerApplicationService;
use App\Progression\Application\Player\PlayerRenameResult;
use App\Progression\Application\Player\PlayerReadApplicationService;
use App\Progression\UI\Api\PlayerNameRequestExtractor;
use App\Progression\UI\Api\PlayerPayloadMapper;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionApiUserContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players')]
final class PlayerController extends AbstractController
{
    public function __construct(
        private readonly PlayerApplicationService $playerApplicationService,
        private readonly PlayerReadApplicationService $playerReadApplicationService,
        private readonly PlayerPayloadMapper $playerPayloadMapper,
        private readonly PlayerNameRequestExtractor $playerNameRequestExtractor,
        private readonly ProgressionApiUserContext $progressionApiUserContext,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    #[Route('', name: 'api_players_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $players = $this->playerReadApplicationService->listForUser($user);

        return $this->json($this->playerPayloadMapper->mapList($players));
    }

    #[Route('', name: 'api_players_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $name = $this->playerNameRequestExtractor->extract($request);
        if (null === $name) {
            return $this->progressionApiErrorResponder->invalidPlayerName();
        }

        $result = $this->playerApplicationService->createForUser($user, $name);
        if (!$result->isOk()) {
            return $this->progressionApiErrorResponder->playerNameAlreadyExists();
        }

        $player = $result->getPlayer();
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNameAlreadyExists();
        }

        return $this->json($this->playerPayloadMapper->map($player), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerReadApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        return $this->json($this->playerPayloadMapper->map($player));
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_update', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerReadApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        $name = $this->playerNameRequestExtractor->extract($request);
        if (null === $name) {
            return $this->progressionApiErrorResponder->invalidPlayerName();
        }

        $renameResult = $this->playerApplicationService->renameOwned($player, $name);
        if (PlayerRenameResult::NAME_CONFLICT === $renameResult) {
            return $this->progressionApiErrorResponder->playerNameAlreadyExists();
        }

        return $this->json($this->playerPayloadMapper->map($player));
    }

    #[Route('/{id<[A-Za-z0-9]{26}>}', name: 'api_players_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerReadApplicationService->findOwnedByPublicId($user, $id);
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        $this->playerApplicationService->delete($player);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function getAuthenticatedUser(): UserEntity
    {
        return $this->progressionApiUserContext->requireAuthenticatedUser($this->getUser());
    }
}
