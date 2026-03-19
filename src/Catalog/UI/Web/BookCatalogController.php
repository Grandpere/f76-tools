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

namespace App\Catalog\UI\Web;

use App\Catalog\Application\Item\BookCatalogFrontApplicationService;
use App\Catalog\Application\Item\ItemCatalogTimestampReadRepository;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Player\PlayerReadApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BookCatalogController extends AbstractController
{
    /**
     * @var array<string, string>
     */
    private const SIGNAL_ICON_MAP = [
        'containers' => '/assets/icons/FO76_Lunchbox_Icon.svg',
        'enemies' => '/assets/icons/FO76_ui_workshopraid_team.png',
        'events' => '/assets/icons/FO76_icon_map_event.png',
        'expeditions' => '/assets/icons/FO76_collections_stamps01.webp',
        'quests' => '/assets/icons/FO76_ui_icon_quest.png',
        'daily_ops' => '/assets/icons/FO76_dailyops_uplink.png',
        'random_encounters' => '/assets/icons/Icon_question_random_encounter.webp',
        'raid' => '/assets/icons/GleamingDepthsMarker.svg',
        'seasonal_content' => '/assets/icons/FO76_scoresprite_seasons.png',
        'treasure_maps' => '/assets/icons/Icon_note_small.png',
        'unused_content' => '/assets/icons/Icon_unused_wiki.png',
        'vendors' => '/assets/icons/Caps.png',
        'world_spawns' => '/assets/icons/FO76_ui_exploration_team.png',
    ];

    /**
     * @var array<string, string>
     */
    private const CURRENCY_ICON_MAP = [
        'caps' => '/assets/icons/Caps.png',
        'gold_bullion' => '/assets/icons/Fo76_Icon_Gold_Bullion.png',
        'stamps' => '/assets/icons/FO76_collections_stamps01.webp',
        'tickets' => '/assets/icons/Tickets_Icon.webp',
    ];

    public function __construct(
        private readonly BookCatalogFrontApplicationService $bookCatalogFrontApplicationService,
        private readonly PlayerReadApplicationService $playerReadApplicationService,
        private readonly ItemCatalogTimestampReadRepository $itemCatalogTimestampReadRepository,
    ) {
    }

    #[Route('/{_locale<en|fr|de>}/plans-recipes', name: 'app_book_catalog', methods: ['GET'], defaults: ['_locale' => 'en'])]
    #[Route('/plans-recipes', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $activePlayerId = $this->playerReadApplicationService->findFirstPublicIdForUser($user);
        $selectedPlayerId = trim((string) $request->query->get('player', $activePlayerId ?? ''));
        $selectedKnowledge = trim((string) $request->query->get('knowledge', 'all'));
        $player = '' !== $selectedPlayerId ? $this->playerReadApplicationService->findOwnedByPublicId($user, $selectedPlayerId) : null;

        $query = trim((string) $request->query->get('q', ''));
        /** @var list<string> $selectedLists */
        $selectedLists = $request->query->all('lists');
        /** @var list<string> $selectedKinds */
        $selectedKinds = $request->query->all('kinds');
        /** @var list<string> $selectedVendorFilters */
        $selectedVendorFilters = $request->query->all('vendorFilters');
        /** @var list<string> $selectedSignals */
        $selectedSignals = $request->query->all('signals');
        $selectedSort = trim((string) $request->query->get('sort', 'name_asc'));
        $perPage = max(12, min(96, (int) $request->query->get('perPage', 24)));
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $this->bookCatalogFrontApplicationService->browse(
            '' !== $query ? $query : null,
            $selectedLists,
            $selectedKinds,
            $selectedVendorFilters,
            $selectedSignals,
            $page,
            $perPage,
            $player,
            $selectedKnowledge,
            $selectedSort,
        );

        $catalogUpdatedAt = $this->itemCatalogTimestampReadRepository->findLatestUpdatedAtByType(ItemTypeEnum::BOOK);

        return $this->render('catalog/books.html.twig', [
            'username' => $user->getEmail(),
            'apiPlayersUrl' => $this->generateUrl('api_players_index'),
            'apiPlayersBaseUrl' => $this->generateUrl('api_players_index'),
            'activePlayerId' => $activePlayerId,
            'storageKey' => sprintf('f76:item-catalog:ui:%d', (int) ($user->getId() ?? 0)),
            'query' => $query,
            'selectedPlayerId' => $selectedPlayerId,
            'selectedKnowledge' => $selectedKnowledge,
            'selectedLists' => $selectedLists,
            'selectedKinds' => $selectedKinds,
            'selectedVendorFilters' => $selectedVendorFilters,
            'selectedSignals' => $selectedSignals,
            'selectedSort' => $selectedSort,
            'listOptions' => $result['listOptions'],
            'kindOptions' => $result['kindOptions'],
            'sortOptions' => $result['sortOptions'],
            'vendorFilterOptions' => $result['vendorFilterOptions'],
            'vendorInfoOptions' => $result['vendorInfoOptions'],
            'signalOptions' => $result['signalOptions'],
            'progressSummary' => $result['progressSummary'],
            'signalIconMap' => self::SIGNAL_ICON_MAP,
            'currencyIconMap' => self::CURRENCY_ICON_MAP,
            'items' => $result['rows'],
            'totalItems' => $result['totalItems'],
            'page' => $result['currentPage'],
            'totalPages' => $result['totalPages'],
            'perPage' => $perPage,
            'catalogUpdatedAt' => $catalogUpdatedAt,
        ]);
    }
}
