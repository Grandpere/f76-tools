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

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class ItemTranslationBackofficeApplicationService
{
    private const DOMAIN = 'items';
    private const CACHE_KEY_PREFIX = 'admin_item_translations.base_rows.';

    public function __construct(
        private readonly TranslationCatalogReader $catalogReader,
        private readonly TranslationCatalogWriter $catalogWriter,
        private readonly CacheInterface $cache,
    ) {
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
        $this->cache->delete($this->baseRowsCacheKey($targetLocale));

        return count($upserts);
    }

    /**
     * @return list<array{key: string, en: string, de: string, target: string, section: string}>
     */
    public function buildRows(string $targetLocale, ?string $query): array
    {
        /** @var list<array{key: string, en: string, de: string, target: string, section: string}> $rows */
        $rows = $this->cache->get($this->baseRowsCacheKey($targetLocale), function (ItemInterface $item) use ($targetLocale): array {
            $item->expiresAfter(300);

            $catalogEn = $this->catalogReader->load('en', self::DOMAIN);
            $catalogDe = $this->catalogReader->load('de', self::DOMAIN);
            $catalogTarget = $this->catalogReader->load($targetLocale, self::DOMAIN);

            $keys = array_keys($catalogEn);
            sort($keys);

            $builtRows = [];
            foreach ($keys as $key) {
                if (!str_starts_with($key, 'item.misc.') && !str_starts_with($key, 'item.book.')) {
                    continue;
                }

                $en = $catalogEn[$key] ?? '';
                $de = $catalogDe[$key] ?? $en;
                $target = $catalogTarget[$key] ?? '';
                $section = str_starts_with($key, 'item.misc.') ? 'misc' : 'book';

                $builtRows[] = [
                    'key' => $key,
                    'en' => $en,
                    'de' => $de,
                    'target' => $target,
                    'section' => $section,
                ];
            }

            return $builtRows;
        });

        if (null === $query) {
            return $rows;
        }

        $filteredRows = [];
        foreach ($rows as $row) {
            $haystack = mb_strtolower($row['key'].' '.$row['en'].' '.$row['de'].' '.$row['target']);
            if (!str_contains($haystack, $query)) {
                continue;
            }
            $filteredRows[] = $row;
        }

        return $filteredRows;
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

    private function baseRowsCacheKey(string $targetLocale): string
    {
        return self::CACHE_KEY_PREFIX.$targetLocale;
    }
}
