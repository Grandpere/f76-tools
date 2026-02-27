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

interface TranslationCatalogWriter
{
    /**
     * @param array<string, string> $entries
     */
    public function upsert(string $locale, string $domain, array $entries): void;
}
