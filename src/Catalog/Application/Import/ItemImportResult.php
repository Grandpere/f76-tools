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

final class ItemImportResult
{
    /**
     * @param array{files:int,rows:int,created:int,updated:int,skipped:int,errors:int,warnings:int,translations_en:int,translations_de:int} $stats
     * @param list<string>                                                                                                                  $warnings
     */
    public function __construct(
        private readonly array $stats,
        private readonly array $warnings,
    ) {
    }

    /**
     * @return array{files:int,rows:int,created:int,updated:int,skipped:int,errors:int,warnings:int,translations_en:int,translations_de:int}
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * @return list<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasErrors(): bool
    {
        return $this->stats['errors'] > 0;
    }
}
