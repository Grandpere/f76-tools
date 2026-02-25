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

use App\Translation\TranslationCatalogReader;
use App\Translation\TranslationCatalogWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/translations/items')]
final class ItemTranslationController extends AbstractController
{
    private const DOMAIN = 'items';
    private const LOCALE_FALLBACK = 'fr';
    private const LOCALE_PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';

    public function __construct(
        private readonly TranslationCatalogReader $catalogReader,
        private readonly TranslationCatalogWriter $catalogWriter,
    ) {
    }

    #[Route('', name: 'app_admin_item_translations', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $locale = $this->normalizeLocale($request->query->get('locale'));
        $query = $this->normalizeQuery($request->query->get('q'));

        if ($request->isMethod('POST')) {
            $postedLocale = $this->normalizeLocale($request->request->get('locale'));
            $locale = $postedLocale;
            /** @var array<string, mixed> $entries */
            $entries = $request->request->all('entries');
            $upserts = $this->normalizeEntries($entries);
            if ([] !== $upserts) {
                $this->catalogWriter->upsert($locale, self::DOMAIN, $upserts);
                $this->addFlash('success', sprintf('Traductions "%s" enregistrees (%d cles).', $locale, count($upserts)));
            } else {
                $this->addFlash('warning', 'Aucune traduction valide a enregistrer.');
            }

            return $this->redirectToRoute('app_admin_item_translations', [
                'locale' => $locale,
                'q' => $query,
            ]);
        }

        $catalogEn = $this->catalogReader->load('en', self::DOMAIN);
        $catalogDe = $this->catalogReader->load('de', self::DOMAIN);
        $catalogLocale = $this->catalogReader->load($locale, self::DOMAIN);
        $rows = $this->buildRows($catalogEn, $catalogDe, $catalogLocale, $query);

        return $this->render('admin/item_translations.html.twig', [
            'locale' => $locale,
            'query' => $query ?? '',
            'rows' => $rows,
            'totalRows' => count($rows),
        ]);
    }

    /**
     * @param array<string, mixed> $entries
     *
     * @return array<string, string>
     */
    private function normalizeEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $key => $value) {
            if ('' === trim($key)) {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }

            $cleanValue = trim((string) $value);
            if ('' === $cleanValue) {
                continue;
            }

            $normalized[trim($key)] = $cleanValue;
        }

        return $normalized;
    }

    private function normalizeLocale(mixed $value): string
    {
        if (!is_string($value)) {
            return self::LOCALE_FALLBACK;
        }

        $locale = trim($value);
        if ('' === $locale) {
            return self::LOCALE_FALLBACK;
        }
        if (1 !== preg_match(self::LOCALE_PATTERN, $locale)) {
            return self::LOCALE_FALLBACK;
        }

        return $locale;
    }

    private function normalizeQuery(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }

    /**
     * @param array<string, string> $catalogEn
     * @param array<string, string> $catalogDe
     * @param array<string, string> $catalogLocale
     *
     * @return list<array{key: string, en: string, de: string, target: string, section: string}>
     */
    private function buildRows(array $catalogEn, array $catalogDe, array $catalogLocale, ?string $query): array
    {
        $keys = array_keys($catalogEn);
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            if (!str_starts_with($key, 'item.misc.') && !str_starts_with($key, 'item.book.')) {
                continue;
            }

            $en = $catalogEn[$key] ?? '';
            $de = $catalogDe[$key] ?? $en;
            $target = $catalogLocale[$key] ?? '';
            $section = str_starts_with($key, 'item.misc.') ? 'misc' : 'book';

            if (null !== $query) {
                $haystack = mb_strtolower($key.' '.$en.' '.$de.' '.$target);
                if (!str_contains($haystack, $query)) {
                    continue;
                }
            }

            $rows[] = [
                'key' => $key,
                'en' => $en,
                'de' => $de,
                'target' => $target,
                'section' => $section,
            ];
        }

        return $rows;
    }
}
