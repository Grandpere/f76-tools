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

namespace App\Progression\UI\Api;

use App\Progression\Application\Knowledge\PlayerKnowledgeTransferApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/knowledge')]
final class PlayerKnowledgeTransferController extends AbstractController
{
    use ProgressionAuthenticatedUserControllerTrait;

    public function __construct(
        private readonly PlayerKnowledgeTransferApplicationService $playerKnowledgeTransferApplicationService,
        private readonly PlayerOwnedContextResolver $playerOwnedContextResolver,
        private readonly PlayerKnowledgeImportContextResolver $playerKnowledgeImportContextResolver,
        private readonly PlayerKnowledgeTransferResultResponder $playerKnowledgeTransferResultResponder,
        private readonly ProgressionApiUserContext $progressionApiUserContext,
    ) {
    }

    #[Route('/export', name: 'api_player_knowledge_export', methods: ['GET'])]
    public function export(string $playerId): JsonResponse
    {
        $player = $this->playerOwnedContextResolver->resolveOrNotFound($playerId, $this->getAuthenticatedUser());
        if ($player instanceof JsonResponse) {
            return $player;
        }

        return $this->json($this->playerKnowledgeTransferApplicationService->export($player));
    }

    #[Route('/import', name: 'api_player_knowledge_import', methods: ['POST'])]
    public function import(string $playerId, Request $request): JsonResponse
    {
        return $this->importLike($playerId, $request, PlayerKnowledgeImportMode::IMPORT);
    }

    #[Route('/preview-import', name: 'api_player_knowledge_preview_import', methods: ['POST'])]
    public function previewImport(string $playerId, Request $request): JsonResponse
    {
        return $this->importLike($playerId, $request, PlayerKnowledgeImportMode::PREVIEW);
    }

    private function importLike(string $playerId, Request $request, PlayerKnowledgeImportMode $mode): JsonResponse
    {
        $context = $this->playerKnowledgeImportContextResolver->resolveOrNotFound($playerId, $request, $this->getAuthenticatedUser());
        if ($context instanceof JsonResponse) {
            return $context;
        }
        $player = $context->player;
        $payload = $context->payload;

        $result = PlayerKnowledgeImportMode::PREVIEW === $mode
            ? $this->playerKnowledgeTransferApplicationService->previewImport($player, $payload)
            : $this->playerKnowledgeTransferApplicationService->import($player, $payload);

        return $this->playerKnowledgeTransferResultResponder->respond($result);
    }

    protected function progressionApiUserContext(): ProgressionApiUserContext
    {
        return $this->progressionApiUserContext;
    }
}
