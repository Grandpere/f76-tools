<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap\Locale;

final class EnglishRoadmapLocaleProfile implements RoadmapLocaleProfile
{
    public function supports(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'en');
    }

    public function usesMonthFirstDates(): bool
    {
        return true;
    }

    public function monthMap(): array
    {
        return [
            1 => 'JANUARY',
            2 => 'FEBRUARY',
            3 => 'MARCH',
            4 => 'APRIL',
            5 => 'MAY',
            6 => 'JUNE',
            7 => 'JULY',
            8 => 'AUGUST',
            9 => 'SEPTEMBER',
            10 => 'OCTOBER',
            11 => 'NOVEMBER',
            12 => 'DECEMBER',
        ];
    }

    public function monthAliases(): array
    {
        return [
            'JAN' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'APR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'JULY' => 7,
            'JUHLET' => 7,
            'AUG' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,
        ];
    }

    public function normalizeTitle(string $title): string
    {
        return $title;
    }
}
