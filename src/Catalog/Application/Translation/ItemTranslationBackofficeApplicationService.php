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

namespace App\Catalog\Application\Translation;

final class ItemTranslationBackofficeApplicationService
{
    private const DOMAIN = 'items';
    private const TARGET_LOCALE_FALLBACK = 'fr';
    private const LOCALE_PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';
    private const LOCKED_LOCALES = ['en', 'de'];

    public function __construct(
        private readonly TranslationCatalogReader $catalogReader,
        private readonly TranslationCatalogWriter $catalogWriter,
    ) {
    }

    public function sanitizeTargetLocale(?string $value): string
    {
        $locale = strtolower($this->normalizeLocale($value));
        if (in_array($locale, self::LOCKED_LOCALES, true)) {
            return self::TARGET_LOCALE_FALLBACK;
        }

        return $locale;
    }

    public function normalizeQuery(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }

    /**
     * @param array<string, mixed> $entries
     */
    public function saveTargetEntries(string $targetLocale, array $entries): int
    {
        $upserts = $this->normalizeEntries($entries);
        if ([] === $upserts) {
            return 0;
        }

        $this->catalogWriter->upsert($targetLocale, self::DOMAIN, $upserts);

        return count($upserts);
    }

    /**
     * @return list<array{key: string, en: string, de: string, target: string, section: string}>
     */
    public function buildRows(string $targetLocale, ?string $query): array
    {
        $catalogEn = $this->catalogReader->load('en', self::DOMAIN);
        $catalogDe = $this->catalogReader->load('de', self::DOMAIN);
        $catalogTarget = $this->catalogReader->load($targetLocale, self::DOMAIN);

        $keys = array_keys($catalogEn);
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            if (!str_starts_with($key, 'item.misc.') && !str_starts_with($key, 'item.book.')) {
                continue;
            }

            $en = $catalogEn[$key] ?? '';
            $de = $catalogDe[$key] ?? $en;
            $target = $catalogTarget[$key] ?? '';
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

    private function normalizeLocale(?string $value): string
    {
        if (null === $value) {
            return self::TARGET_LOCALE_FALLBACK;
        }

        $locale = trim($value);
        if ('' === $locale) {
            return self::TARGET_LOCALE_FALLBACK;
        }
        if (1 !== preg_match(self::LOCALE_PATTERN, $locale)) {
            return self::TARGET_LOCALE_FALLBACK;
        }

        return $locale;
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
}
