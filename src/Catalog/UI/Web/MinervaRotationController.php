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

use App\Catalog\Application\Item\ItemCatalogTimestampReadRepository;
use App\Catalog\Application\Minerva\MinervaRotationTimelineApplicationService;
use App\Catalog\Domain\Item\ItemTypeEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Player\PlayerReadApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MinervaRotationController extends AbstractController
{
    public function __construct(
        private readonly MinervaRotationTimelineApplicationService $timelineApplicationService,
        private readonly PlayerReadApplicationService $playerReadApplicationService,
        private readonly ItemCatalogTimestampReadRepository $itemCatalogTimestampReadRepository,
    ) {
    }

    #[Route('/{_locale<en|fr|de>}/minerva-rotation', name: 'app_minerva_rotation', methods: ['GET'], defaults: ['_locale' => 'en'])]
    #[Route('/minerva-rotation', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $activePlayerId = $this->playerReadApplicationService->findFirstPublicIdForUser($user);

        $catalogUpdatedAt = $this->itemCatalogTimestampReadRepository->findLatestUpdatedAtByType(ItemTypeEnum::BOOK);

        $timeline = $this->timelineApplicationService->buildTimeline();
        $tablePagination = $this->buildTablePagination($timeline, (int) $request->query->get('page', 1), 10);

        return $this->render('minerva/rotation.html.twig', [
            'timeline' => $timeline,
            'timelineTablePagination' => $tablePagination,
            'username' => $user->getEmail(),
            'apiPlayersUrl' => $this->generateUrl('api_players_index'),
            'apiPlayersBaseUrl' => $this->generateUrl('api_players_index'),
            'activePlayerId' => $activePlayerId,
            'catalogUpdatedAt' => $catalogUpdatedAt,
            'storageKey' => sprintf('f76:item-catalog:ui:%d', (int) ($user->getId() ?? 0)),
        ]);
    }

    /**
     * @param array{
     *     rows: list<array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         source: string,
     *         status: string
     *     }>
     * } $timeline
     *
     * @return array{
     *     rows: list<array{
     *         id: int,
     *         location: string,
     *         listCycle: int,
     *         startsAt: string,
     *         endsAt: string,
     *         source: string,
     *         status: string
     *     }>,
     *     currentPage: int,
     *     pageCount: int,
     *     perPage: int,
     *     totalRows: int
     * }
     */
    private function buildTablePagination(array $timeline, int $page, int $perPage): array
    {
        $activeOrUpcomingRows = array_values(array_filter(
            $timeline['rows'],
            static fn (array $row): bool => 'ended' !== $row['status'],
        ));
        $totalRows = count($activeOrUpcomingRows);
        $pageCount = max(1, (int) ceil($totalRows / $perPage));
        $currentPage = max(1, min($page, $pageCount));
        $offset = ($currentPage - 1) * $perPage;

        /** @var list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string,source:string,status:string}> $rows */
        $rows = array_slice($activeOrUpcomingRows, $offset, $perPage);

        return [
            'rows' => $rows,
            'currentPage' => $currentPage,
            'pageCount' => $pageCount,
            'perPage' => $perPage,
            'totalRows' => $totalRows,
        ];
    }
}
