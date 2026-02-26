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
use App\Progression\Application\Knowledge\PlayerKnowledgeApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeTransferApplicationService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/knowledge')]
final class PlayerKnowledgeTransferController extends AbstractController
{
    public function __construct(
        private readonly PlayerKnowledgeApplicationService $playerKnowledgeApplicationService,
        private readonly PlayerKnowledgeTransferApplicationService $playerKnowledgeTransferApplicationService,
    ) {
    }

    #[Route('/export', name: 'api_player_knowledge_export', methods: ['GET'])]
    public function export(string $playerId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->playerKnowledgeTransferApplicationService->export($player));
    }

    #[Route('/import', name: 'api_player_knowledge_import', methods: ['POST'])]
    public function import(string $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $result = $this->playerKnowledgeTransferApplicationService->import($player, $this->decodeJson($request));
        if (!$result['ok']) {
            return $this->json($result, JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
    }

    #[Route('/preview-import', name: 'api_player_knowledge_preview_import', methods: ['POST'])]
    public function previewImport(string $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $result = $this->playerKnowledgeTransferApplicationService->previewImport($player, $this->decodeJson($request));
        if (!$result['ok']) {
            return $this->json($result, JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($result);
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

}
