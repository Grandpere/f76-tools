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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BookCatalogController extends AbstractController
{
    public function __construct(
        private readonly BookCatalogFrontApplicationService $bookCatalogFrontApplicationService,
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

        $query = trim((string) $request->query->get('q', ''));
        $list = trim((string) $request->query->get('list', ''));
        $perPage = max(12, min(96, (int) $request->query->get('perPage', 24)));
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $this->bookCatalogFrontApplicationService->browse(
            '' !== $query ? $query : null,
            '' !== $list ? $list : null,
            $page,
            $perPage,
        );

        $catalogUpdatedAt = $this->itemCatalogTimestampReadRepository->findLatestUpdatedAtByType(ItemTypeEnum::BOOK);

        return $this->render('catalog/books.html.twig', [
            'username' => $user->getEmail(),
            'query' => $query,
            'selectedList' => '' !== $list ? $list : null,
            'listOptions' => $result['listOptions'],
            'items' => $result['rows'],
            'totalItems' => $result['totalItems'],
            'page' => $result['currentPage'],
            'totalPages' => $result['totalPages'],
            'perPage' => $perPage,
            'totalLists' => $result['totalLists'],
            'minervaItems' => $result['minervaItems'],
            'catalogUpdatedAt' => $catalogUpdatedAt,
        ]);
    }
}
