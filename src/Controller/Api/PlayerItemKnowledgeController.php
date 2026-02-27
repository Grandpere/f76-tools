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
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeWriteApplicationService;
use App\Progression\UI\Api\PlayerItemActionContext;
use App\Progression\UI\Api\PlayerItemActionContextResolver;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadMapper;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadSearchFilter;
use App\Progression\UI\Api\PlayerOwnedContextResolver;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionApiUserContext;
use App\Progression\UI\Api\ProgressionItemTypeQueryParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/items')]
final class PlayerItemKnowledgeController extends AbstractController
{
    public function __construct(
        private readonly PlayerKnowledgeCatalogApplicationService $playerKnowledgeCatalogApplicationService,
        private readonly PlayerKnowledgeWriteApplicationService $playerKnowledgeWriteApplicationService,
        private readonly PlayerItemActionContextResolver $playerItemActionContextResolver,
        private readonly PlayerKnowledgeItemPayloadMapper $playerKnowledgeItemPayloadMapper,
        private readonly PlayerKnowledgeItemPayloadSearchFilter $playerKnowledgeItemPayloadSearchFilter,
        private readonly PlayerOwnedContextResolver $playerOwnedContextResolver,
        private readonly ProgressionApiUserContext $progressionApiUserContext,
        private readonly ProgressionItemTypeQueryParser $progressionItemTypeQueryParser,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    #[Route('', name: 'api_player_items_index', methods: ['GET'])]
    public function index(string $playerId, Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        $player = $this->playerOwnedContextResolver->resolveOrNotFound($playerId, $user);
        if ($player instanceof JsonResponse) {
            return $player;
        }

        $type = $this->progressionItemTypeQueryParser->parse($request->query->get('type'));
        if (false === $type) {
            return $this->progressionApiErrorResponder->invalidItemType();
        }

        $catalogRows = $this->playerKnowledgeCatalogApplicationService->listForPlayer($player, $type);
        $payload = $this->playerKnowledgeItemPayloadMapper->mapCatalogRows($catalogRows);
        $payload = $this->playerKnowledgeItemPayloadSearchFilter->filter($payload, $request->query->get('q'));

        return $this->json($payload);
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_set', methods: ['PUT'])]
    public function setLearned(string $playerId, string $itemId): JsonResponse
    {
        $context = $this->resolveActionContextOrResponse($playerId, $itemId);
        if ($context instanceof JsonResponse) {
            return $context;
        }
        $player = $context->player;
        $item = $context->item;

        $this->playerKnowledgeWriteApplicationService->markLearned($player, $item);

        return $this->json($this->playerKnowledgeItemPayloadMapper->map($item, true));
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_unset', methods: ['DELETE'])]
    public function unsetLearned(string $playerId, string $itemId): Response
    {
        $context = $this->resolveActionContextOrResponse($playerId, $itemId);
        if ($context instanceof JsonResponse) {
            return $context;
        }
        $player = $context->player;
        $item = $context->item;

        $this->playerKnowledgeWriteApplicationService->unmarkLearned($player, $item);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function getAuthenticatedUser(): UserEntity
    {
        return $this->progressionApiUserContext->requireAuthenticatedUser($this->getUser());
    }

    private function resolveActionContextOrResponse(string $playerId, string $itemId): PlayerItemActionContext|JsonResponse
    {
        return $this->playerItemActionContextResolver->resolveOrNotFound($playerId, $itemId, $this->getAuthenticatedUser());
    }
}
