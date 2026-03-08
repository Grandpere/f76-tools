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

final class GermanRoadmapLocaleProfile implements RoadmapLocaleProfile
{
    public function supports(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'de');
    }

    public function usesMonthFirstDates(): bool
    {
        return false;
    }

    public function monthMap(): array
    {
        return [
            1 => 'JANUAR',
            2 => 'FEBRUAR',
            3 => 'MARZ',
            4 => 'APRIL',
            5 => 'MAI',
            6 => 'JUNI',
            7 => 'JULI',
            8 => 'AUGUST',
            9 => 'SEPTEMBER',
            10 => 'OKTOBER',
            11 => 'NOVEMBER',
            12 => 'DEZEMBER',
        ];
    }

    public function monthAliases(): array
    {
        return [
            'JAN' => 1,
            'FEB' => 2,
            'MRZ' => 3,
            'MAR' => 3,
            'MAERZ' => 3,
            'APR' => 4,
            'MA' => 5,
            'MAI' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AUG' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OKT' => 10,
            'NOV' => 11,
            'DEZ' => 12,
        ];
    }

    public function normalizeTitle(string $title): string
    {
        return $title;
    }
}
