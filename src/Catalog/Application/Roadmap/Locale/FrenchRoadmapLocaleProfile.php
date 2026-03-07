<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap\Locale;

final class FrenchRoadmapLocaleProfile implements RoadmapLocaleProfile
{
    public function supports(string $locale): bool
    {
        return str_starts_with(strtolower(trim($locale)), 'fr');
    }

    public function usesMonthFirstDates(): bool
    {
        return false;
    }

    public function monthMap(): array
    {
        return [
            1 => 'JANVIER',
            2 => 'FEVRIER',
            3 => 'MARS',
            4 => 'AVRIL',
            5 => 'MAI',
            6 => 'JUIN',
            7 => 'JUILLET',
            8 => 'AOUT',
            9 => 'SEPTEMBRE',
            10 => 'OCTOBRE',
            11 => 'NOVEMBRE',
            12 => 'DECEMBRE',
        ];
    }

    public function monthAliases(): array
    {
        return [
            'JAN' => 1,
            'FEV' => 2,
            'FEB' => 2,
            'MARS' => 3,
            'MAR' => 3,
            'AVR' => 4,
            'MAI' => 5,
            'JUN' => 6,
            'JUI' => 7,
            'JUIL' => 7,
            'JUILET' => 7,
            'JUHLET' => 7,
            'AOU' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,
        ];
    }

    public function normalizeTitle(string $title): string
    {
        return preg_replace('/\bF[ÉE]TE\b/u', 'FÊTE', $title) ?? $title;
    }
}
