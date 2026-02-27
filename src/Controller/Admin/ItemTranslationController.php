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

namespace App\Controller\Admin;

use App\Catalog\Application\Translation\ItemTranslationBackofficeApplicationService;
use App\Catalog\Application\Translation\ItemTranslationListQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/translations/items')]
final class ItemTranslationController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminInputSanitizerTrait;

    public function __construct(
        private readonly ItemTranslationBackofficeApplicationService $itemTranslationBackofficeApplicationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_item_translations', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $this->ensureAdminAccess();

        $listQuery = ItemTranslationListQuery::fromRaw(
            $this->optionalString($request->query->get('target')),
            $this->optionalString($request->query->get('q')),
            $this->optionalIntOrString($request->query->get('page')),
            $this->optionalIntOrString($request->query->get('perPage')),
        );
        $targetLocale = $listQuery->targetLocale;
        $query = $listQuery->query;
        $perPage = $listQuery->perPage;
        $page = $listQuery->page;

        if ($request->isMethod('POST')) {
            $postListQuery = ItemTranslationListQuery::fromRaw(
                $this->optionalString($request->request->get('target')),
                $query,
                $this->optionalIntOrString($request->request->get('page')),
                $this->optionalIntOrString($request->request->get('perPage')),
            );
            $targetLocale = $postListQuery->targetLocale;
            $perPage = $postListQuery->perPage;
            $page = $postListQuery->page;
            /** @var array<string, mixed> $entries */
            $entries = $request->request->all('entries');
            $savedCount = $this->itemTranslationBackofficeApplicationService->saveTargetEntries($targetLocale, $entries);
            if ($savedCount > 0) {
                $this->addFlash('success', $this->translator->trans('admin_translations.flash.saved', [
                    '%locale%' => $targetLocale,
                    '%count%' => (string) $savedCount,
                ]));
            } else {
                $this->addFlash('warning', $this->translator->trans('admin_translations.flash.nothing_to_save'));
            }

            return $this->redirectToRoute('app_admin_item_translations', [
                'locale' => $request->getLocale(),
                'target' => $targetLocale,
                'q' => $query,
                'page' => $page,
                'perPage' => $perPage,
            ]);
        }

        $rows = $this->itemTranslationBackofficeApplicationService->buildRows($targetLocale, $query);
        $totalRows = count($rows);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $pageRows = array_slice($rows, $offset, $perPage);

        return $this->render('admin/item_translations.html.twig', [
            'targetLocale' => $targetLocale,
            'query' => $query ?? '',
            'rows' => $pageRows,
            'totalRows' => $totalRows,
            'pageRows' => count($pageRows),
            'perPage' => $perPage,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }
}
