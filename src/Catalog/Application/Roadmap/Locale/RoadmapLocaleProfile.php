<?php

declare(strict_types=1);

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
