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
use App\Progression\Application\Knowledge\PlayerKnowledgeApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeStatsApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/stats')]
final class PlayerStatsController extends AbstractController
{
    public function __construct(
        private readonly PlayerKnowledgeApplicationService $playerKnowledgeApplicationService,
        private readonly PlayerKnowledgeStatsApplicationService $playerKnowledgeStatsApplicationService,
    ) {
    }

    #[Route('', name: 'api_player_stats_show', methods: ['GET'])]
    public function __invoke(string $playerId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $player = $this->playerKnowledgeApplicationService->resolveOwnedPlayer($user, $playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->playerKnowledgeStatsApplicationService->getStats($player));
    }
}
