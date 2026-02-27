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

final readonly class ItemTranslationListQuery
{
    private const DEFAULT_PER_PAGE = 40;
    private const MAX_PER_PAGE = 200;
    private const TARGET_LOCALE_FALLBACK = 'fr';
    private const LOCALE_PATTERN = '/^[a-z]{2}(?:_[A-Z]{2})?$/';
    private const LOCKED_LOCALES = ['en', 'de'];

    public function __construct(
        public string $targetLocale,
        public ?string $query,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRaw(?string $rawTargetLocale, ?string $rawQuery, ?int $rawPage, ?int $rawPerPage): self
    {
        return new self(
            targetLocale: self::sanitizeTargetLocale($rawTargetLocale),
            query: self::sanitizeQuery($rawQuery),
            page: self::sanitizePositiveInt($rawPage, 1),
            perPage: self::sanitizePositiveInt($rawPerPage, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE),
        );
    }

    private static function sanitizeTargetLocale(?string $value): string
    {
        $locale = strtolower(self::normalizeLocale($value));
        if (in_array($locale, self::LOCKED_LOCALES, true)) {
            return self::TARGET_LOCALE_FALLBACK;
        }

        return $locale;
    }

    private static function normalizeLocale(?string $value): string
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

    private static function sanitizeQuery(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $query = mb_strtolower(trim($value));

        return '' === $query ? null : $query;
    }

    private static function sanitizePositiveInt(?int $value, int $default, ?int $max = null): int
    {
        if (!is_int($value)) {
            return $default;
        }
        $number = $value;

        if ($number < 1) {
            return $default;
        }

        if (null !== $max && $number > $max) {
            return $max;
        }

        return $number;
    }
}
