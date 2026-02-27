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
use App\Progression\Application\Knowledge\PlayerKnowledgeTransferApplicationService;
use App\Progression\UI\Api\PlayerKnowledgeImportMode;
use App\Progression\UI\Api\PlayerKnowledgeTransferResultResponder;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolverTrait;
use App\Progression\UI\Api\ProgressionApiJsonPayloadDecoder;
use App\Progression\UI\Api\ProgressionOwnedPlayerApiResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/knowledge')]
final class PlayerKnowledgeTransferController extends AbstractController
{
    use ProgressionOwnedPlayerApiResolverTrait;

    public function __construct(
        private readonly PlayerKnowledgeTransferApplicationService $playerKnowledgeTransferApplicationService,
        private readonly ProgressionOwnedPlayerApiResolver $progressionOwnedPlayerApiResolver,
        private readonly ProgressionApiJsonPayloadDecoder $progressionApiJsonPayloadDecoder,
        private readonly PlayerKnowledgeTransferResultResponder $playerKnowledgeTransferResultResponder,
    ) {
    }

    #[Route('/export', name: 'api_player_knowledge_export', methods: ['GET'])]
    public function export(string $playerId): JsonResponse
    {
        $player = $this->resolveOwnedPlayerOrNotFound($playerId);
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
        $context = $this->resolveImportContextOrNotFound($playerId, $request);
        if ($context instanceof JsonResponse) {
            return $context;
        }
        $player = $context['player'];
        $payload = $context['payload'];

        $result = PlayerKnowledgeImportMode::PREVIEW === $mode
            ? $this->playerKnowledgeTransferApplicationService->previewImport($player, $payload)
            : $this->playerKnowledgeTransferApplicationService->import($player, $payload);

        return $this->playerKnowledgeTransferResultResponder->respond($result);
    }

    protected function progressionOwnedPlayerApiResolver(): ProgressionOwnedPlayerApiResolver
    {
        return $this->progressionOwnedPlayerApiResolver;
    }

    /**
     * @return array{player: PlayerEntity, payload: array<string, mixed>}|JsonResponse
     */
    private function resolveImportContextOrNotFound(string $playerId, Request $request): array|JsonResponse
    {
        $player = $this->resolveOwnedPlayerOrNotFound($playerId);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        return [
            'player' => $player,
            'payload' => $this->progressionApiJsonPayloadDecoder->decode($request),
        ];
    }
}
