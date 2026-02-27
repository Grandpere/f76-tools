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

use App\Progression\Application\Knowledge\PlayerKnowledgeStatsApplicationService;
use App\Progression\UI\Api\PlayerOwnedContextResolver;
use App\Progression\UI\Api\ProgressionApiUserContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/stats')]
final class PlayerStatsController extends AbstractController
{
    use ProgressionAuthenticatedUserControllerTrait;

    public function __construct(
        private readonly PlayerKnowledgeStatsApplicationService $playerKnowledgeStatsApplicationService,
        private readonly PlayerOwnedContextResolver $playerOwnedContextResolver,
        private readonly ProgressionApiUserContext $progressionApiUserContext,
    ) {
    }

    #[Route('', name: 'api_player_stats_show', methods: ['GET'])]
    public function __invoke(string $playerId): JsonResponse
    {
        $player = $this->playerOwnedContextResolver->resolveOrNotFound($playerId, $this->getAuthenticatedUser());
        if ($player instanceof JsonResponse) {
            return $player;
        }

        return $this->json($this->playerKnowledgeStatsApplicationService->getStats($player));
    }

    protected function progressionApiUserContext(): ProgressionApiUserContext
    {
        return $this->progressionApiUserContext;
    }
}
