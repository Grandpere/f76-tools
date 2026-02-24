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
use App\Entity\ItemEntity;
use App\Entity\PlayerEntity;
use App\Entity\PlayerItemKnowledgeEntity;
use App\Entity\UserEntity;
use App\Repository\ItemEntityRepository;
use App\Repository\PlayerEntityRepository;
use App\Repository\PlayerItemKnowledgeEntityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/players/{playerId<\d+>}/items')]
final class PlayerItemKnowledgeController extends AbstractController
{
    public function __construct(
        private readonly PlayerEntityRepository $playerRepository,
        private readonly ItemEntityRepository $itemRepository,
        private readonly PlayerItemKnowledgeEntityRepository $knowledgeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'api_player_items_index', methods: ['GET'])]
    public function index(int $playerId, Request $request): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $type = $this->parseType($request->query->get('type'));
        if (false === $type) {
            return $this->json(['error' => 'Invalid item type.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $items = $this->itemRepository->findAllOrdered($type);
        $learnedMap = array_flip($this->knowledgeRepository->findLearnedItemIdsByPlayer($player));

        $payload = array_map(
            fn (ItemEntity $item): array => $this->toItemPayload($item, isset($learnedMap[$item->getId() ?? 0])),
            $items,
        );

        return $this->json($payload);
    }

    #[Route('/{itemId<\d+>}/learned', name: 'api_player_items_learned_set', methods: ['PUT'])]
    public function setLearned(int $playerId, int $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->itemRepository->findOneById($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $knowledge = $this->knowledgeRepository->findOneByPlayerAndItem($player, $item);
        if (null === $knowledge) {
            $knowledge = (new PlayerItemKnowledgeEntity())
                ->setPlayer($player)
                ->setItem($item)
                ->setLearnedAt(new DateTimeImmutable());
            $this->entityManager->persist($knowledge);
            $this->entityManager->flush();
        }

        return $this->json($this->toItemPayload($item, true));
    }

    #[Route('/{itemId<\d+>}/learned', name: 'api_player_items_learned_unset', methods: ['DELETE'])]
    public function unsetLearned(int $playerId, int $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->itemRepository->findOneById($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $knowledge = $this->knowledgeRepository->findOneByPlayerAndItem($player, $item);
        if ($knowledge instanceof PlayerItemKnowledgeEntity) {
            $this->entityManager->remove($knowledge);
            $this->entityManager->flush();
        }

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

    private function resolveOwnedPlayer(int $playerId): ?PlayerEntity
    {
        $user = $this->getAuthenticatedUser();

        return $this->playerRepository->findOneByIdAndUser($playerId, $user);
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

    /**
     * @return array{
     *     id: int|null,
     *     sourceId: int,
     *     type: string,
     *     nameKey: string,
     *     name: string,
     *     descKey: string|null,
     *     description: string|null,
     *     rank: int|null,
     *     listNumbers: list<int>,
     *     isInSpecialList: bool,
     *     learned: bool
     * }
     */
    private function toItemPayload(ItemEntity $item, bool $learned): array
    {
        $listNumbers = [];
        $isInSpecialList = false;

        foreach ($item->getBookLists() as $bookList) {
            $listNumbers[] = $bookList->getListNumber();
            $isInSpecialList = $isInSpecialList || $bookList->isSpecialList();
        }

        sort($listNumbers);

        $description = null;
        if (null !== $item->getDescKey()) {
            $description = $this->translator->trans($item->getDescKey(), domain: 'items');
        }

        return [
            'id' => $item->getId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'nameKey' => $item->getNameKey(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'descKey' => $item->getDescKey(),
            'description' => $description,
            'rank' => $item->getRank(),
            'listNumbers' => array_values(array_unique($listNumbers)),
            'isInSpecialList' => $isInSpecialList,
            'learned' => $learned,
        ];
    }
}
