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
use App\Entity\UserEntity;
use App\Repository\ItemEntityRepository;
use App\Repository\PlayerItemKnowledgeEntityRepository;
use App\Service\PlayerItemKnowledgeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/players/{playerId<[A-Za-z0-9]{26}>}/items')]
final class PlayerItemKnowledgeController extends AbstractController
{
    public function __construct(
        private readonly ItemEntityRepository $itemRepository,
        private readonly PlayerItemKnowledgeEntityRepository $knowledgeRepository,
        private readonly PlayerItemKnowledgeManager $knowledgeManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'api_player_items_index', methods: ['GET'])]
    public function index(string $playerId, Request $request): JsonResponse
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
        $query = $this->normalizeSearchQuery($request->query->get('q'));
        $learnedMap = array_flip($this->knowledgeRepository->findLearnedItemIdsByPlayer($player));

        $payload = array_map(
            fn (ItemEntity $item): array => $this->toItemPayload($item, isset($learnedMap[$item->getId() ?? 0])),
            $items,
        );
        if (null !== $query) {
            $payload = array_values(array_filter(
                $payload,
                static function (array $row) use ($query): bool {
                    $name = mb_strtolower($row['name']);
                    $description = mb_strtolower((string) ($row['description'] ?? ''));
                    $nameKey = mb_strtolower($row['nameKey']);
                    $descKey = mb_strtolower((string) ($row['descKey'] ?? ''));

                    return str_contains($name, $query)
                        || str_contains($description, $query)
                        || str_contains($nameKey, $query)
                        || str_contains($descKey, $query);
                },
            ));
        }

        return $this->json($payload);
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_set', methods: ['PUT'])]
    public function setLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->itemRepository->findOneByPublicId($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->knowledgeManager->setLearned($player, $item);

        return $this->json($this->toItemPayload($item, true));
    }

    #[Route('/{itemId<[A-Za-z0-9]{26}>}/learned', name: 'api_player_items_learned_unset', methods: ['DELETE'])]
    public function unsetLearned(string $playerId, string $itemId): JsonResponse
    {
        $player = $this->resolveOwnedPlayer($playerId);
        if (null === $player) {
            return $this->json(['error' => 'Player not found.'], JsonResponse::HTTP_NOT_FOUND);
        }
        $item = $this->itemRepository->findOneByPublicId($itemId);
        if (null === $item) {
            return $this->json(['error' => 'Item not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->knowledgeManager->unsetLearned($player, $item);

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

    private function resolveOwnedPlayer(string $playerId): ?PlayerEntity
    {
        $user = $this->getAuthenticatedUser();

        return $this->knowledgeManager->resolveOwnedPlayer($playerId, $user);
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

    private function normalizeSearchQuery(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }

    /**
     * @return array{
     *     id: string,
     *     sourceId: int,
     *     type: string,
     *     nameKey: string,
     *     name: string,
     *     descKey: string|null,
     *     description: string|null,
     *     isNew: bool,
     *     price: int|null,
     *     priceMinerva: int|null,
     *     dropRaid: bool,
     *     dropBurningSprings: bool,
     *     dropDailyOps: bool,
     *     vendorRegs: bool,
     *     vendorSamuel: bool,
     *     vendorMortimer: bool,
     *     infoHtml: string|null,
     *     dropSourcesHtml: string|null,
     *     relationsHtml: string|null,
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
            'id' => $item->getPublicId(),
            'sourceId' => $item->getSourceId(),
            'type' => $item->getType()->value,
            'nameKey' => $item->getNameKey(),
            'name' => $this->translator->trans($item->getNameKey(), domain: 'items'),
            'descKey' => $item->getDescKey(),
            'description' => $description,
            'isNew' => $item->isNew(),
            'price' => $item->getPrice(),
            'priceMinerva' => $item->getPriceMinerva(),
            'dropRaid' => $item->isDropRaid(),
            'dropBurningSprings' => $item->isDropBurningSprings(),
            'dropDailyOps' => $item->isDropDailyOps(),
            'vendorRegs' => $item->isVendorRegs(),
            'vendorSamuel' => $item->isVendorSamuel(),
            'vendorMortimer' => $item->isVendorMortimer(),
            'infoHtml' => $item->getInfoHtml(),
            'dropSourcesHtml' => $item->getDropSourcesHtml(),
            'relationsHtml' => $item->getRelationsHtml(),
            'rank' => $item->getRank(),
            'listNumbers' => array_values(array_unique($listNumbers)),
            'isInSpecialList' => $isInSpecialList,
            'learned' => $learned,
        ];
    }
}
