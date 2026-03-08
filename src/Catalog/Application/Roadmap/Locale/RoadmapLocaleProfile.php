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

namespace App\Catalog\Application\Roadmap\Locale;

interface RoadmapLocaleProfile
{
    public function supports(string $locale): bool;

    public function usesMonthFirstDates(): bool;

    /**
     * @return array<int, string>
     */
    public function monthMap(): array;

    /**
     * @return array<string, int>
     */
    public function monthAliases(): array;

    public function normalizeTitle(string $title): string;
}
