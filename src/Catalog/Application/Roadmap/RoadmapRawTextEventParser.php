<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use DateTimeImmutable;

final class RoadmapRawTextEventParser
{
    /**
     * @return list<RoadmapParsedEvent>
     */
    public function parse(string $rawText, string $locale, DateTimeImmutable $referenceDate): array
    {
        $lines = $this->normalizeLines($rawText);
        $events = [];

        foreach ($lines as $index => $line) {
            $dateRange = $this->extractDateRange($line, $locale, $referenceDate);
            if (null === $dateRange) {
                continue;
            }

            $title = $this->resolveTitle($lines, $index, $locale);
            if ('' === $title) {
                $title = sprintf('Event %d', count($events) + 1);
            }

            $events[] = new RoadmapParsedEvent(
                $title,
                $dateRange['startsAt'],
                $dateRange['endsAt'],
                null,
                null,
            );
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function normalizeLines(string $rawText): array
    {
        $parts = preg_split('/\R/u', $rawText);
        if (!is_array($parts)) {
            return [];
        }

        $lines = [];
        foreach ($parts as $part) {
            $line = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function resolveTitle(array $lines, int $dateLineIndex, string $locale): string
    {
        $candidate = $this->findCandidateTitleForward($lines, $dateLineIndex, $locale);
        if ('' !== $candidate) {
            return $candidate;
        }

        return $this->findCandidateTitleBackward($lines, $dateLineIndex, $locale);
    }

    /**
     * @param list<string> $lines
     */
    private function findCandidateTitleForward(array $lines, int $dateLineIndex, string $locale): string
    {
        for ($offset = 1; $offset <= 3; ++$offset) {
            $candidateIndex = $dateLineIndex + $offset;
            if (!isset($lines[$candidateIndex])) {
                break;
            }

            $candidate = $lines[$candidateIndex];
            if ($this->extractDateRange($candidate, $locale, new DateTimeImmutable()) !== null) {
                continue;
            }
            if ($this->looksLikeMonthOnlyLabel($candidate, $locale)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    /**
     * @param list<string> $lines
     */
    private function findCandidateTitleBackward(array $lines, int $dateLineIndex, string $locale): string
    {
        for ($offset = 1; $offset <= 2; ++$offset) {
            $candidateIndex = $dateLineIndex - $offset;
            if (!isset($lines[$candidateIndex])) {
                break;
            }

            $candidate = $lines[$candidateIndex];
            if ($this->extractDateRange($candidate, $locale, new DateTimeImmutable()) !== null) {
                continue;
            }
            if ($this->looksLikeMonthOnlyLabel($candidate, $locale)) {
                continue;
            }

            return $candidate;
        }

        return '';
    }

    /**
     * @return array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable}|null
     */
    private function extractDateRange(string $line, string $locale, DateTimeImmutable $referenceDate): ?array
    {
        if (!preg_match(
            '/(\d{1,2})\s*([[:alpha:]\x{00C0}-\x{017F}\.]+)\s*[-–]\s*(\d{1,2})\s*([[:alpha:]\x{00C0}-\x{017F}\.]+)/u',
            $line,
            $matches,
        )) {
            return null;
        }

        $startDay = (int) $matches[1];
        $startMonth = $this->monthNameToNumber($matches[2], $locale);
        $endDay = (int) $matches[3];
        $endMonth = $this->monthNameToNumber($matches[4], $locale);
        if ($startMonth <= 0 || $endMonth <= 0) {
            return null;
        }

        $year = (int) $referenceDate->format('Y');
        $endYear = $endMonth < $startMonth ? $year + 1 : $year;

        $startsAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 00:00:00', $year, $startMonth, $startDay));
        $endsAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 23:59:59', $endYear, $endMonth, $endDay));
        if (!$startsAt instanceof DateTimeImmutable || !$endsAt instanceof DateTimeImmutable) {
            return null;
        }

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ];
    }

    private function looksLikeMonthOnlyLabel(string $line, string $locale): bool
    {
        $normalized = $this->normalizeWord($line);

        return in_array($normalized, $this->monthMap($locale), true);
    }

    private function monthNameToNumber(string $rawMonth, string $locale): int
    {
        $normalized = $this->normalizeWord($rawMonth);
        $map = array_flip($this->monthMap($locale));

        return isset($map[$normalized]) ? (int) $map[$normalized] : 0;
    }

    /**
     * @return array<int, string>
     */
    private function monthMap(string $locale): array
    {
        $normalized = strtolower(trim($locale));
        if (str_starts_with($normalized, 'fr')) {
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

        if (str_starts_with($normalized, 'de')) {
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

    private function normalizeWord(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        $normalized = str_replace(
            ['.', ',', ';', ':', 'É', 'È', 'Ê', 'Ë', 'Ä', 'Ö', 'Ü', 'ß', 'À', 'Â', 'Î', 'Ï', 'Ô', 'Û', 'Ù', 'Ç'],
            ['', '', '', '', 'E', 'E', 'E', 'E', 'A', 'O', 'U', 'SS', 'A', 'A', 'I', 'I', 'O', 'U', 'U', 'C'],
            $normalized,
        );

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }
}
