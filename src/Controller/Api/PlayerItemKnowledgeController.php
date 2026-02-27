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
use App\Progression\Application\Knowledge\PlayerKnowledgeCatalogApplicationService;
use App\Progression\Application\Knowledge\PlayerKnowledgeApplicationService;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadMapper;
use App\Progression\UI\Api\PlayerKnowledgeItemPayloadSearchFilter;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionOwnedPlayerResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/items')]
final class PlayerItemKnowledgeController extends AbstractController
{
    public function __construct(
        private readonly PlayerKnowledgeCatalogApplicationService $playerKnowledgeCatalogApplicationService,
        private readonly PlayerKnowledgeApplicationService $playerKnowledgeApplicationService,
        private readonly PlayerKnowledgeItemPayloadMapper $playerKnowledgeItemPayloadMapper,
        private readonly PlayerKnowledgeItemPayloadSearchFilter $playerKnowledgeItemPayloadSearchFilter,
        private readonly ProgressionOwnedPlayerResolver $progressionOwnedPlayerResolver,
        private readonly ProgressionApiErrorResponder $progressionApiErrorResponder,
    ) {
    }

    #[Route('', name: 'api_player_items_index', methods: ['GET'])]
    public function index(string $playerId, Request $request): JsonResponse
    {
        $player = $this->progressionOwnedPlayerResolver->resolve($playerId, $this->getUser());
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }

        $type = $this->parseType($request->query->get('type'));
        if (false === $type) {
            return $this->progressionApiErrorResponder->invalidItemType();
        }

        $catalogRows = $this->playerKnowledgeCatalogApplicationService->listForPlayer($player, $type);

        $payload = [];
        foreach ($catalogRows as $row) {
            $payload[] = $this->playerKnowledgeItemPayloadMapper->map($row['item'], $row['learned']);
        }
        $payload = $this->playerKnowledgeItemPayloadSearchFilter->filter($payload, $request->query->get('q'));

        return $this->json($payload);
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_set', methods: ['PUT'])]
    public function setLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->progressionOwnedPlayerResolver->resolve($playerId, $this->getUser());
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }
        $item = $this->playerKnowledgeApplicationService->resolveItemByPublicId($itemId);
        if (null === $item) {
            return $this->progressionApiErrorResponder->itemNotFound();
        }

        $this->playerKnowledgeApplicationService->markLearned($player, $item);

        return $this->json($this->playerKnowledgeItemPayloadMapper->map($item, true));
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_unset', methods: ['DELETE'])]
    public function unsetLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->progressionOwnedPlayerResolver->resolve($playerId, $this->getUser());
        if (null === $player) {
            return $this->progressionApiErrorResponder->playerNotFound();
        }
        $item = $this->playerKnowledgeApplicationService->resolveItemByPublicId($itemId);
        if (null === $item) {
            return $this->progressionApiErrorResponder->itemNotFound();
        }

        $this->playerKnowledgeApplicationService->unmarkLearned($player, $item);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
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

}
