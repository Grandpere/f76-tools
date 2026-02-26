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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/translations/items')]
final class ItemTranslationController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 40;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly ItemTranslationBackofficeApplicationService $itemTranslationBackofficeApplicationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_item_translations', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $targetLocale = $this->itemTranslationBackofficeApplicationService->sanitizeTargetLocale($request->query->get('target'));
        $query = $this->itemTranslationBackofficeApplicationService->normalizeQuery($request->query->get('q'));
        $perPage = $this->sanitizePositiveInt($request->query->get('perPage'), self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = $this->sanitizePositiveInt($request->query->get('page'), 1);

        if ($request->isMethod('POST')) {
            $targetLocale = $this->itemTranslationBackofficeApplicationService->sanitizeTargetLocale($request->request->get('target'));
            $perPage = $this->sanitizePositiveInt($request->request->get('perPage'), self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
            $page = $this->sanitizePositiveInt($request->request->get('page'), 1);
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

    private function sanitizePositiveInt(mixed $value, int $default, ?int $max = null): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && '' !== trim($value) && ctype_digit(trim($value))) {
            $number = (int) trim($value);
        } else {
            return $default;
        }

        if ($number < 1) {
            return $default;
        }

        if (null !== $max && $number > $max) {
            return $max;
        }

        return $number;
    }
}
