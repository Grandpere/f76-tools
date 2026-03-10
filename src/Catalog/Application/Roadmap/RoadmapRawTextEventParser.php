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

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Application\Roadmap\Locale\RoadmapLocaleProfileRegistry;
use DateTimeImmutable;

final class RoadmapRawTextEventParser
{
    private const DEFAULT_START_HOUR = 18;
    private const DEFAULT_END_HOUR = 18;
    private const SINGLE_DAY_START_HOUR = 16;
    private const SINGLE_DAY_END_HOUR = 20;

    private RoadmapLocaleProfileRegistry $profileRegistry;

    public function __construct(?RoadmapLocaleProfileRegistry $profileRegistry = null)
    {
        $this->profileRegistry = $profileRegistry ?? new RoadmapLocaleProfileRegistry();
    }

    /**
     * @return list<RoadmapParsedEvent>
     */
    public function parse(string $rawText, string $locale, DateTimeImmutable $referenceDate): array
    {
        $lines = $this->normalizeLines($rawText);
        $events = [];
        $lastRangeEnd = null;

        foreach ($lines as $index => $line) {
            $dateRange = $this->extractDateRange($line, $locale, $referenceDate, $lastRangeEnd);
            if (null !== $dateRange) {
                $title = $this->resolveTitle($lines, $index, $locale);
                if ('' === $title) {
                    $title = sprintf('Event %d', count($events) + 1);
                }

                $events[] = new RoadmapParsedEvent(
                    $title,
                    $dateRange['startsAt'],
                    $dateRange['endsAt'],
                );
                $lastRangeEnd = $dateRange['endsAt'];

                continue;
            }

            $singleDate = $this->extractSingleDate($line, $locale, $referenceDate);
            if (null === $singleDate) {
                continue;
            }

            $title = $this->resolveTitleForSingleDate($lines, $index, $locale, $singleDate['inlineTitle']);
            if ('' === $title) {
                $title = sprintf('Event %d', count($events) + 1);
            }

            $events[] = new RoadmapParsedEvent(
                $title,
                $singleDate['startsAt'],
                $singleDate['endsAt'],
            );
            $lastRangeEnd = $singleDate['endsAt'];
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

        $preNormalized = [];
        foreach ($parts as $part) {
            $line = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            if ('' !== $line) {
                $preNormalized[] = $this->normalizeDateArtifacts($line);
            }
        }

        $lines = [];
        $count = count($preNormalized);
        for ($index = 0; $index < $count; ++$index) {
            $line = $preNormalized[$index];

            if (str_ends_with($line, '-') && isset($preNormalized[$index + 1])) {
                $next = ltrim($preNormalized[$index + 1], "- \t");
                $line = rtrim(substr($line, 0, -1)).' - '.$next;
                ++$index;
            } elseif (str_starts_with($line, '- ') && [] !== $lines) {
                $suffix = ltrim($line, "- \t");
                $lastIndex = array_key_last($lines);
                $lines[$lastIndex] = $this->normalizeDateArtifacts(rtrim($lines[$lastIndex]).' - '.$suffix);
                continue;
            }

            $lines[] = $this->normalizeDateArtifacts($line);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function resolveTitle(array $lines, int $dateLineIndex, string $locale): string
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $candidate = $this->findCandidateTitleForward($lines, $dateLineIndex, $locale);
        if ('' !== $candidate) {
            return $profile->normalizeTitle($candidate);
        }

        return $profile->normalizeTitle($this->findCandidateTitleBackward($lines, $dateLineIndex, $locale));
    }

    /**
     * @param list<string> $lines
     */
    private function resolveTitleForSingleDate(array $lines, int $dateLineIndex, string $locale, string $inlineTitle): string
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $parts = [];
        if ('' !== $inlineTitle && !$this->isIgnoredTitleLine($inlineTitle)) {
            $parts[] = $inlineTitle;
        }

        $forwardTitle = $this->findCandidateTitleForward($lines, $dateLineIndex, $locale);
        if ('' !== $forwardTitle) {
            $parts[] = $forwardTitle;
        }

        if ([] === $parts) {
            return '';
        }

        return $profile->normalizeTitle($this->normalizeTitle(implode(' ', $parts)));
    }

    /**
     * @param list<string> $lines
     */
    private function findCandidateTitleForward(array $lines, int $dateLineIndex, string $locale): string
    {
        $parts = [];
        for ($offset = 1; $offset <= 4; ++$offset) {
            $candidateIndex = $dateLineIndex + $offset;
            if (!isset($lines[$candidateIndex])) {
                break;
            }

            $candidate = $lines[$candidateIndex];
            if (null !== $this->extractDateRange($candidate, $locale, new DateTimeImmutable(), null)) {
                break;
            }
            if ($this->looksLikeMonthOnlyLabel($candidate, $locale)) {
                break;
            }
            if ($this->isIgnoredTitleLine($candidate)) {
                $lastPart = [] !== $parts ? rtrim($parts[count($parts) - 1]) : '';
                if ('' !== $lastPart && str_ends_with($lastPart, ':')) {
                    $parts[] = $candidate;
                }
                continue;
            }

            $parts[] = $candidate;
            if (count($parts) >= 3) {
                break;
            }
        }

        if ([] === $parts) {
            return '';
        }

        return $this->normalizeTitle(implode(' ', $parts));
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
            if (null !== $this->extractDateRange($candidate, $locale, new DateTimeImmutable(), null)) {
                continue;
            }
            if ($this->looksLikeMonthOnlyLabel($candidate, $locale)) {
                continue;
            }
            if ($this->isIgnoredTitleLine($candidate)) {
                continue;
            }

            return $this->normalizeTitle($candidate);
        }

        return '';
    }

    /**
     * @return array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable}|null
     */
    private function extractDateRange(string $line, string $locale, DateTimeImmutable $referenceDate, ?DateTimeImmutable $lastRangeEnd): ?array
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $connectorPattern = '(?:-|–|BIS|TO)';

        // Format: "3 MARCH - 10 MARCH" / "30. APRIL BIS 4. MAI"
        if (!preg_match(
            '/(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)\s*'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)/iu',
            $line,
            $matches,
        )) {
            // Format: "3. BIS 10. MÄRZ" (start month omitted)
            if (preg_match(
                '/(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)/iu',
                $line,
                $matches,
            )) {
                $startDay = (int) $matches[1];
                $endDay = (int) $matches[2];
                $endMonth = $this->monthNameToNumber($matches[3], $locale);
                $startMonth = $endMonth;
            // Format: "APRIL BIS 5. MAI" (start day omitted)
            } elseif (preg_match(
                '/([^\s\-–]+)\s*'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)/iu',
                $line,
                $matches,
            )) {
                $startMonth = $this->monthNameToNumber($matches[1], $locale);
                $endDay = (int) $matches[2];
                $endMonth = $this->monthNameToNumber($matches[3], $locale);
                $startDay = $this->inferMissingStartDay($endDay, $startMonth, $endMonth, $referenceDate, $lastRangeEnd);
            // Format: "BIS 20. APRIL" (start day+month omitted)
            } elseif (preg_match(
                '/^'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)/iu',
                $line,
                $matches,
            )) {
                $endDay = (int) $matches[1];
                $endMonth = $this->monthNameToNumber($matches[2], $locale);
                $startMonth = $endMonth;
                $startDay = $this->inferMissingStartDay($endDay, $startMonth, $endMonth, $referenceDate, $lastRangeEnd);
            } else {
                // Format: "APRIL 7 - APRIL 14" / "MAR 3 - MAR 10"
                if ($profile->usesMonthFirstDates() && preg_match(
                    '/([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*'.$connectorPattern.'\s*([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?/iu',
                    $line,
                    $matches,
                )) {
                    $startMonth = $this->monthNameToNumber($matches[1], $locale);
                    $startDay = (int) $matches[2];
                    $endMonth = $this->monthNameToNumber($matches[3], $locale);
                    $endDay = (int) $matches[4];
                // Format: "APRIL 21 - MAY 5" with noisy separator variants
                } elseif ($profile->usesMonthFirstDates() && preg_match(
                    '/([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*([^\s\-–]+)/iu',
                    $line,
                    $matches,
                )) {
                    $startMonth = $this->monthNameToNumber($matches[1], $locale);
                    $startDay = (int) $matches[2];
                    $endDay = (int) $matches[3];
                    $endMonth = $this->monthNameToNumber($matches[4], $locale);
                } else {
                    return null;
                }
            }
        } else {
            $startDay = (int) $matches[1];
            $startMonth = $this->monthNameToNumber($matches[2], $locale);
            $endDay = (int) $matches[3];
            $endMonth = $this->monthNameToNumber($matches[4], $locale);
        }

        if ($startMonth <= 0 && $endMonth > 0) {
            $startMonth = $endMonth;
        } elseif ($endMonth <= 0 && $startMonth > 0) {
            $endMonth = $startMonth;
        }

        if ($startMonth <= 0 || $endMonth <= 0) {
            return null;
        }

        $startDay = $this->correctSuspiciousCrossMonthStartDay(
            $startDay,
            $startMonth,
            $endDay,
            $endMonth,
            $referenceDate,
            $lastRangeEnd,
        );

        $year = (int) $referenceDate->format('Y');
        $endYear = $endMonth < $startMonth ? $year + 1 : $year;

        $isSingleDay = $year === $endYear && $startMonth === $endMonth && $startDay === $endDay;
        $startsAt = $this->buildDateTime(
            $year,
            $startMonth,
            $startDay,
            $isSingleDay ? self::SINGLE_DAY_START_HOUR : self::DEFAULT_START_HOUR,
        );
        $endsAt = $this->buildDateTime(
            $endYear,
            $endMonth,
            $endDay,
            $isSingleDay ? self::SINGLE_DAY_END_HOUR : self::DEFAULT_END_HOUR,
        );
        if (null === $startsAt || null === $endsAt) {
            return null;
        }

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ];
    }

    /**
     * @return array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, inlineTitle: string}|null
     */
    private function extractSingleDate(string $line, string $locale, DateTimeImmutable $referenceDate): ?array
    {
        $profile = $this->profileRegistry->profileFor($locale);
        if (str_contains($line, '-') || str_contains($line, '–') || 1 === preg_match('/\b(BIS|TO)\b/iu', $line)) {
            return null;
        }

        if (preg_match('/^\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s+([^\s]+)\s*(.*)$/iu', $line, $matches)) {
            $day = (int) $matches[1];
            $month = $this->monthNameToNumber($matches[2], $locale);
            $inlineTail = (string) $matches[3];
        } elseif ($profile->usesMonthFirstDates() && preg_match('/^\s*([^\s]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*(.*)$/iu', $line, $matches)) {
            $month = $this->monthNameToNumber($matches[1], $locale);
            $day = (int) $matches[2];
            $inlineTail = (string) $matches[3];
        } else {
            return null;
        }

        if ($month <= 0) {
            return null;
        }

        $year = (int) $referenceDate->format('Y');
        $startsAt = $this->buildDateTime($year, $month, $day, self::SINGLE_DAY_START_HOUR);
        $endsAt = $this->buildDateTime($year, $month, $day, self::SINGLE_DAY_END_HOUR);
        if (null === $startsAt || null === $endsAt) {
            return null;
        }

        $inlineTitle = $this->normalizeTitle($inlineTail);

        return [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'inlineTitle' => $inlineTitle,
        ];
    }

    private function looksLikeMonthOnlyLabel(string $line, string $locale): bool
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $normalized = $this->normalizeMonthToken($line);

        if (in_array($normalized, $profile->monthMap(), true)) {
            return true;
        }

        return isset($profile->monthAliases()[$normalized]);
    }

    private function monthNameToNumber(string $rawMonth, string $locale): int
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $normalized = $this->normalizeMonthToken($rawMonth);
        $aliases = $profile->monthAliases();
        if (isset($aliases[$normalized])) {
            return (int) $aliases[$normalized];
        }

        $map = $profile->monthMap();
        $flipped = array_flip($map);

        if (isset($flipped[$normalized])) {
            return (int) $flipped[$normalized];
        }

        // Accept common OCR truncations: "JUILL", "SEPTE", etc.
        if (mb_strlen($normalized) >= 2) {
            $prefixMatches = [];
            foreach ($map as $monthNumber => $label) {
                if (str_starts_with($label, $normalized) || str_starts_with($normalized, $label)) {
                    $prefixMatches[] = (int) $monthNumber;
                }
            }
            if (1 === count($prefixMatches)) {
                return $prefixMatches[0];
            }
        }

        // Fallback for OCR typos: "AVRlL", "MA1", "JUlN", etc.
        if (mb_strlen($normalized) >= 2) {
            $bestMonth = 0;
            $bestDistance = PHP_INT_MAX;
            foreach ($map as $monthNumber => $label) {
                $distance = levenshtein($normalized, $label);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestMonth = (int) $monthNumber;
                }
            }

            if ($bestMonth > 0 && $bestDistance <= 3) {
                return $bestMonth;
            }
        }

        return 0;
    }

    private function normalizeWord(string $value): string
    {
        $normalized = mb_strtoupper(trim($this->normalizeOcrUnicodeArtifacts($value)));
        $normalized = str_replace(
            ['.', ',', ';', ':', 'É', 'È', 'Ê', 'Ë', 'Ä', 'Ö', 'Ü', 'ß', 'À', 'Â', 'Î', 'Ï', 'Ô', 'Û', 'Ù', 'Ç'],
            ['', '', '', '', 'E', 'E', 'E', 'E', 'A', 'O', 'U', 'SS', 'A', 'A', 'I', 'I', 'O', 'U', 'U', 'C'],
            $normalized,
        );

        return preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    }

    private function normalizeMonthToken(string $value): string
    {
        $normalized = $this->normalizeWord($value);
        // Common OCR confusions in month tokens
        $normalized = str_replace(['1', '|', '!', '§', '5', '0', '8', '/'], ['I', 'I', 'I', 'S', 'S', 'O', 'B', ''], $normalized);
        $normalized = preg_replace('/[^A-Z]/u', '', $normalized) ?? $normalized;

        return $normalized;
    }

    private function normalizeDateArtifacts(string $line): string
    {
        $normalized = trim($this->normalizeOcrUnicodeArtifacts($line));
        $normalized = preg_replace('/\bTER\b/u', '1ER', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bIER\b/u', '1ER', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bLER\b/u', '1ER', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function normalizeTitle(string $value): string
    {
        $cleaned = $this->normalizeOcrUnicodeArtifacts($value);
        $title = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
        $title = preg_replace('/\s+([,.;:!?])/u', '$1', $title) ?? $title;
        $title = preg_replace('/(?:\s+00)+\s*$/u', '', $title) ?? $title;

        return trim($title);
    }

    private function normalizeOcrUnicodeArtifacts(string $value): string
    {
        return strtr($value, [
            'Ę' => 'E',
            'ę' => 'e',
            'Ł' => 'L',
            'ł' => 'l',
            'Ń' => 'N',
            'ń' => 'n',
            'Ś' => 'S',
            'ś' => 's',
            'Ź' => 'Z',
            'ź' => 'z',
            'Ż' => 'Z',
            'ż' => 'z',
            'Ř' => 'R',
            'ř' => 'r',
            'İ' => 'I',
            'ı' => 'i',
            'Š' => 'S',
            'š' => 's',
            'Ž' => 'Z',
            'ž' => 'z',
            'Č' => 'C',
            'č' => 'c',
            'Ť' => 'T',
            'ť' => 't',
        ]);
    }

    private function inferMissingStartDay(int $endDay, int $startMonth, int $endMonth, DateTimeImmutable $referenceDate, ?DateTimeImmutable $lastRangeEnd): int
    {
        $year = (int) $referenceDate->format('Y');
        $daysInStartMonth = $this->daysInMonth($year, $startMonth);

        if (
            $lastRangeEnd instanceof DateTimeImmutable
            && (int) $lastRangeEnd->format('Y') === (int) $referenceDate->format('Y')
            && (int) $lastRangeEnd->format('m') === $startMonth
        ) {
            $candidate = (int) $lastRangeEnd->format('d') + 1;
            if ($startMonth === $endMonth) {
                $candidate = max(1, min($endDay, $candidate));
                // For "BIS <day>" OCR rows, inferred same-month windows are usually short (around 5 days).
                // If continuity inference yields a longer window, fallback to a 5-day range ending on endDay.
                if (($endDay - $candidate + 1) > 5) {
                    $candidate = max(1, $endDay - 4);
                }

                return $candidate;
            }

            return max(1, min($daysInStartMonth, $candidate));
        }

        if ($startMonth !== $endMonth) {
            return 1;
        }

        return 1;
    }

    private function correctSuspiciousCrossMonthStartDay(
        int $startDay,
        int $startMonth,
        int $endDay,
        int $endMonth,
        DateTimeImmutable $referenceDate,
        ?DateTimeImmutable $lastRangeEnd,
    ): int {
        if ($startMonth === $endMonth) {
            return $startDay;
        }

        if ($startDay > 3 || $endDay > 3 || !$lastRangeEnd instanceof DateTimeImmutable) {
            return $startDay;
        }

        $year = (int) $referenceDate->format('Y');
        if (
            (int) $lastRangeEnd->format('Y') !== $year
            || (int) $lastRangeEnd->format('m') !== $startMonth
        ) {
            return $startDay;
        }

        $lastEndDay = (int) $lastRangeEnd->format('d');
        if ($lastEndDay < 15) {
            return $startDay;
        }

        $daysInStartMonth = $this->daysInMonth($year, $startMonth);
        $candidate = $daysInStartMonth - (4 - $endDay);
        if ($candidate <= $lastEndDay) {
            $candidate = min($daysInStartMonth, $lastEndDay + 1);
        }

        if ($candidate < 1 || $candidate > $daysInStartMonth) {
            return $startDay;
        }

        return $candidate;
    }

    private function daysInMonth(int $year, int $month): int
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
        if (!$date instanceof DateTimeImmutable) {
            return 31;
        }

        return (int) $date->format('t');
    }

    private function buildDateTime(int $year, int $month, int $day, int $hour): ?DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d %02d:00:00', $year, $month, $day, $hour));

        return $dateTime instanceof DateTimeImmutable ? $dateTime : null;
    }

    private function isIgnoredTitleLine(string $line): bool
    {
        $normalized = $this->normalizeWord($line);
        if ('' === $normalized) {
            return true;
        }

        if (1 === preg_match('/^[^[:alnum:]]+$/u', $normalized)) {
            return true;
        }

        if (1 === preg_match('/^\d{4}\s+ZENIMAX$/u', $normalized)) {
            return true;
        }

        if (
            str_contains($normalized, 'COMMUNITY CALENDAR')
            || str_contains($normalized, 'BETHESDA')
            || str_contains($normalized, 'ZENIMAX')
            || str_contains($normalized, 'FALLOUT')
            || str_contains($normalized, 'FALLEUT')
            || 1 === preg_match('/\bTM\b/u', $normalized)
        ) {
            return true;
        }

        if ('RIP DARING' === $normalized || 'BETHESDA' === $normalized) {
            return true;
        }

        return false;
    }
}
