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

namespace App\Catalog\Application\Import;

final class ItemImportExternalUrlResolver
{
    public function __construct(
        private readonly ItemImportValueNormalizer $valueNormalizer,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public function resolve(string $provider, array $row): ?string
    {
        $wikiUrl = $this->valueNormalizer->toNullableString($row['wiki_url'] ?? null);
        if (null !== $wikiUrl) {
            return $wikiUrl;
        }

        if ('nukacrypt' !== strtolower(trim($provider))) {
            return null;
        }

        $formId = $this->valueNormalizer->toNullableString($row['form_id'] ?? null);
        if (null === $formId) {
            return null;
        }

        return sprintf(
            'https://nukacrypt.com/FO76/w/latest/SeventySix.esm/%s',
            strtolower($formId),
        );
    }
}
