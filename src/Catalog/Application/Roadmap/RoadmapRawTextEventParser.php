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
use App\Catalog\Application\Roadmap\Locale\RoadmapLocaleProfile;
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
        $effectiveReferenceDate = $this->resolveReferenceDateFromRawText($rawText, $referenceDate);
        $dateMarkerIndexes = $this->detectDateMarkerIndexes($lines, $locale, $effectiveReferenceDate);
        $events = [];
        $lastRangeEnd = null;
        $consumedIndexes = [];

        foreach ($lines as $index => $line) {
            if (isset($consumedIndexes[$index])) {
                continue;
            }

            $multipleRanges = $this->extractMultipleDateRangesFromLine($line, $locale, $effectiveReferenceDate, $lastRangeEnd);
            if (count($multipleRanges) >= 2) {
                $pairedTitles = $this->collectFollowingTitleLinesForMultiRange($lines, $index, $locale, count($multipleRanges));
                foreach ($multipleRanges as $rangeIndex => $dateRange) {
                    $title = $pairedTitles[$rangeIndex] ?? '';
                    if ('' === $title) {
                        $title = $this->resolveTitle($lines, $index, $locale, $dateMarkerIndexes);
                    }
                    if ('' === $title) {
                        $title = sprintf('Event %d', count($events) + 1);
                    }

                    $events[] = new RoadmapParsedEvent(
                        $title,
                        $dateRange['startsAt'],
                        $dateRange['endsAt'],
                    );
                    $lastRangeEnd = $dateRange['endsAt'];
                }

                continue;
            }

            $dateRange = $this->extractDateRange($line, $locale, $effectiveReferenceDate, $lastRangeEnd);
            if (null !== $dateRange) {
                $title = $this->resolveTitle($lines, $index, $locale, $dateMarkerIndexes);
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

            $singleDate = $this->extractSingleDate($line, $locale, $effectiveReferenceDate);
            if (null === $singleDate) {
                continue;
            }

            if (
                '' === trim($singleDate['inlineTitle'])
                && isset($lines[$index + 1])
            ) {
                $nextSingleDate = $this->extractSingleDate($lines[$index + 1], $locale, $effectiveReferenceDate);
                if (
                    is_array($nextSingleDate)
                    && '' === trim($nextSingleDate['inlineTitle'])
                ) {
                    $inferredTitle = $this->resolveTitleForSingleDate($lines, $index + 1, $locale, '', $dateMarkerIndexes);
                    if ('' !== $inferredTitle) {
                        $startsAt = $singleDate['startsAt']->setTime(self::DEFAULT_START_HOUR, 0);
                        $endsAt = $nextSingleDate['startsAt']->setTime(self::DEFAULT_END_HOUR, 0);
                        if ($endsAt >= $startsAt) {
                            $events[] = new RoadmapParsedEvent($inferredTitle, $startsAt, $endsAt);
                            $lastRangeEnd = $endsAt;
                            $consumedIndexes[$index + 1] = true;

                            continue;
                        }
                    }
                }
            }

            $title = $this->resolveTitleForSingleDate($lines, $index, $locale, $singleDate['inlineTitle'], $dateMarkerIndexes);
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
        $pendingRangePrefix = null;
        $count = count($preNormalized);
        for ($index = 0; $index < $count; ++$index) {
            $line = $preNormalized[$index];

            if (
                is_string($pendingRangePrefix)
                && 1 === preg_match('/^(?:[•\*\-\.,]?\s*)?(?:BIS|TO)\b/iu', $line)
            ) {
                $suffix = preg_replace('/^(?:[•\*\-\.,]?\s*)?(?:BIS|TO)\s*/iu', '', $line) ?? $line;
                $line = $pendingRangePrefix.' '.ltrim($suffix, " \t");
                $pendingRangePrefix = null;
            }

            if (str_ends_with($line, '-') && isset($preNormalized[$index + 1])) {
                $next = ltrim($preNormalized[$index + 1], "- \t");
                $line = rtrim(substr($line, 0, -1)).' - '.$next;
                ++$index;
            } elseif (1 === preg_match('/\b(?:BIS|TO)\s*$/iu', $line) && isset($preNormalized[$index + 1])) {
                $next = ltrim($preNormalized[$index + 1], " \t");
                if ($this->looksLikeDateContinuationFragment($next)) {
                    $line = rtrim($line).' '.$next;
                    ++$index;
                } else {
                    $pendingRangePrefix = rtrim($line);
                }
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
     * @param list<int>    $dateMarkerIndexes
     */
    private function resolveTitle(array $lines, int $dateLineIndex, string $locale, array $dateMarkerIndexes): string
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $nextDateMarkerIndex = $this->nextDateMarkerIndex($dateMarkerIndexes, $dateLineIndex);
        $candidate = $this->findCandidateTitleForward($lines, $dateLineIndex, $locale, $nextDateMarkerIndex);
        if ('' !== $candidate) {
            return $profile->normalizeTitle($candidate);
        }

        return $profile->normalizeTitle($this->findCandidateTitleBackward($lines, $dateLineIndex, $locale, $dateMarkerIndexes));
    }

    /**
     * @param list<string> $lines
     * @param list<int>    $dateMarkerIndexes
     */
    private function resolveTitleForSingleDate(array $lines, int $dateLineIndex, string $locale, string $inlineTitle, array $dateMarkerIndexes): string
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $parts = [];
        if ('' !== $inlineTitle && !$this->isIgnoredTitleLine($inlineTitle)) {
            $parts[] = $inlineTitle;
        }

        $nextDateMarkerIndex = $this->nextDateMarkerIndex($dateMarkerIndexes, $dateLineIndex);
        $forwardTitle = $this->findCandidateTitleForward($lines, $dateLineIndex, $locale, $nextDateMarkerIndex);
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
    private function findCandidateTitleForward(array $lines, int $dateLineIndex, string $locale, ?int $nextDateMarkerIndex): string
    {
        $parts = [];
        for ($offset = 1; $offset <= 6; ++$offset) {
            $candidateIndex = $dateLineIndex + $offset;
            if (!isset($lines[$candidateIndex])) {
                break;
            }
            if (is_int($nextDateMarkerIndex) && $candidateIndex >= $nextDateMarkerIndex) {
                break;
            }

            $candidate = $lines[$candidateIndex];
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
     *
     * @return list<int>
     */
    private function detectDateMarkerIndexes(array $lines, string $locale, DateTimeImmutable $referenceDate): array
    {
        $indexes = [];
        foreach ($lines as $index => $line) {
            if (null !== $this->extractDateRange($line, $locale, $referenceDate, null)) {
                $indexes[] = $index;
                continue;
            }
            if (null !== $this->extractSingleDate($line, $locale, $referenceDate)) {
                $indexes[] = $index;
            }
        }

        if ([] === $indexes) {
            return [];
        }

        sort($indexes);

        return array_values(array_unique($indexes));
    }

    /**
     * @param list<int> $dateMarkerIndexes
     */
    private function nextDateMarkerIndex(array $dateMarkerIndexes, int $currentIndex): ?int
    {
        foreach ($dateMarkerIndexes as $markerIndex) {
            if ($markerIndex > $currentIndex) {
                return $markerIndex;
            }
        }

        return null;
    }

    /**
     * @param list<int> $dateMarkerIndexes
     */
    private function previousDateMarkerIndex(array $dateMarkerIndexes, int $currentIndex): ?int
    {
        $previous = null;
        foreach ($dateMarkerIndexes as $markerIndex) {
            if ($markerIndex >= $currentIndex) {
                break;
            }
            $previous = $markerIndex;
        }

        return $previous;
    }

    /**
     * @param list<string> $lines
     */
    /**
     * @param list<string> $lines
     * @param list<int>    $dateMarkerIndexes
     */
    private function findCandidateTitleBackward(array $lines, int $dateLineIndex, string $locale, array $dateMarkerIndexes): string
    {
        $previousDateMarkerIndex = $this->previousDateMarkerIndex($dateMarkerIndexes, $dateLineIndex);
        if (
            is_int($previousDateMarkerIndex)
            && isset($lines[$previousDateMarkerIndex])
            && null !== $this->extractSingleDate($lines[$previousDateMarkerIndex], $locale, new DateTimeImmutable())
            && ($dateLineIndex - $previousDateMarkerIndex) <= 4
        ) {
            return '';
        }

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
     * @return list<array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable}>
     */
    private function extractMultipleDateRangesFromLine(string $line, string $locale, DateTimeImmutable $referenceDate, ?DateTimeImmutable $lastRangeEnd): array
    {
        $matchCount = preg_match_all(
            '/\d{1,2}\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*[^\s\-–]+\s*(?:-|–|BIS|TO)\s*\d{1,2}\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s*[^\s\-–]+/iu',
            $line,
            $matches,
        );
        if (!is_int($matchCount) || $matchCount < 2) {
            return [];
        }

        $ranges = [];
        $cursorLastRangeEnd = $lastRangeEnd;
        foreach ($matches[0] as $fragment) {
            $range = $this->extractDateRange($fragment, $locale, $referenceDate, $cursorLastRangeEnd);
            if (null === $range) {
                continue;
            }

            $ranges[] = $range;
            $cursorLastRangeEnd = $range['endsAt'];
        }

        return count($ranges) >= 2 ? $ranges : [];
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private function collectFollowingTitleLinesForMultiRange(array $lines, int $dateLineIndex, string $locale, int $limit): array
    {
        $titles = [];
        for ($offset = 1; $offset <= 6; ++$offset) {
            $candidateIndex = $dateLineIndex + $offset;
            if (!isset($lines[$candidateIndex])) {
                break;
            }

            $candidate = $lines[$candidateIndex];
            if (null !== $this->extractDateRange($candidate, $locale, new DateTimeImmutable(), null)) {
                break;
            }
            if (null !== $this->extractSingleDate($candidate, $locale, new DateTimeImmutable())) {
                break;
            }
            if ($this->looksLikeMonthOnlyLabel($candidate, $locale)) {
                break;
            }
            if ($this->isIgnoredTitleLine($candidate)) {
                continue;
            }

            $titles[] = $this->normalizeTitle($candidate);
            if (count($titles) >= $limit) {
                break;
            }
        }

        return $titles;
    }

    /**
     * @return array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable}|null
     */
    private function extractDateRange(string $line, string $locale, DateTimeImmutable $referenceDate, ?DateTimeImmutable $lastRangeEnd): ?array
    {
        $connectorPattern = '(?:-|–|BIS|TO)';

        if (preg_match(
            '/(\d{1,2})\s*[\/\.\-]\s*(\d{1,2})(?:\s*[\/\.\-]\s*(\d{2,4}))?\s*'.$connectorPattern.'\s*(\d{1,2})\s*[\/\.\-]\s*(\d{1,2})(?:\s*[\/\.\-]\s*(\d{2,4}))?/u',
            $line,
            $numeric,
        )) {
            $startDay = (int) $numeric[1];
            $startMonth = (int) $numeric[2];
            $startYear = $this->normalizeParsedYear((string) $numeric[3], $referenceDate) ?? (int) $referenceDate->format('Y');
            $endDay = (int) $numeric[4];
            $endMonth = (int) $numeric[5];
            $endYear = $this->normalizeParsedYear((string) ($numeric[6] ?? ''), $referenceDate);
            if (!is_int($endYear)) {
                $endYear = $endMonth < $startMonth ? $startYear + 1 : $startYear;
            }

            $startDay = max(1, min(31, $startDay));
            $endDay = max(1, min(31, $endDay));
            $startMonth = max(1, min(12, $startMonth));
            $endMonth = max(1, min(12, $endMonth));

            $isSingleDay = $startYear === $endYear && $startMonth === $endMonth && $startDay === $endDay;
            $startsAt = $this->buildDateTime(
                $startYear,
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
            if ($startsAt instanceof DateTimeImmutable && $endsAt instanceof DateTimeImmutable) {
                return [
                    'startsAt' => $startsAt,
                    'endsAt' => $endsAt,
                ];
            }
        }

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
                if (preg_match(
                    '/([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?(?:\s*,?\s*\d{2,4})?\s*'.$connectorPattern.'\s*([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?(?:\s*,?\s*\d{2,4})?/iu',
                    $line,
                    $matches,
                )) {
                    $startMonth = $this->monthNameToNumber($matches[1], $locale, false);
                    $startDay = (int) $matches[2];
                    $endMonth = $this->monthNameToNumber($matches[3], $locale, false);
                    $endDay = (int) $matches[4];
                // Format: "APRIL 21 - MAY 5" with noisy separator variants
                } elseif (preg_match(
                    '/([^\s\-–]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?(?:\s*,?\s*\d{2,4})?\s*'.$connectorPattern.'\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?(?:\s*,?\s*\d{2,4})?\s*([^\s\-–]+)/iu',
                    $line,
                    $matches,
                )) {
                    $startMonth = $this->monthNameToNumber($matches[1], $locale, false);
                    $startDay = (int) $matches[2];
                    $endDay = (int) $matches[3];
                    $endMonth = $this->monthNameToNumber($matches[4], $locale, false);
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
        if (str_contains($line, '-') || str_contains($line, '–') || 1 === preg_match('/\b(BIS|TO)\b/iu', $line)) {
            return null;
        }

        if (preg_match('/^\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?\s+([^\s]+)(?:\s*,?\s*(\d{2,4}))?\s*(.*)$/iu', $line, $matches)) {
            $day = (int) $matches[1];
            $month = $this->monthNameToNumber($matches[2], $locale);
            $year = $this->normalizeParsedYear((string) $matches[3], $referenceDate) ?? (int) $referenceDate->format('Y');
            $inlineTail = (string) $matches[4];
        } elseif (preg_match('/^\s*([^\s\d\/\.\-]+)\s*(\d{1,2})\.?(?:\s*(?:ER|ST|ND|RD|TH))?(?:\s*,?\s*(\d{2,4}))?\s*(.*)$/iu', $line, $matches)) {
            $month = $this->monthNameToNumber($matches[1], $locale, false);
            $day = (int) $matches[2];
            $year = $this->normalizeParsedYear((string) $matches[3], $referenceDate) ?? (int) $referenceDate->format('Y');
            $inlineTail = (string) $matches[4];
        } elseif (preg_match('/^\s*(\d{1,2})\s*[\/\.\-]\s*(\d{1,2})(?:\s*[\/\.\-]\s*(\d{2,4}))?\s*(.*)$/u', $line, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = $this->normalizeParsedYear((string) $matches[3], $referenceDate) ?? (int) $referenceDate->format('Y');
            $inlineTail = (string) $matches[4];
        } else {
            return null;
        }

        if ($month <= 0 || $month > 12) {
            return null;
        }

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

    private function monthNameToNumber(string $rawMonth, string $locale, bool $allowFuzzy = true): int
    {
        $profile = $this->profileRegistry->profileFor($locale);
        $normalized = $this->normalizeMonthToken($rawMonth);
        if (in_array($normalized, ['BIS', 'TO', 'UND', 'AND', 'ET', 'EVENT', 'UPDATE'], true)) {
            return 0;
        }

        $resolved = $this->resolveMonthNumberFromProfile($normalized, $profile);
        if ($resolved > 0) {
            return $resolved;
        }

        foreach ($this->profileRegistry->allProfiles() as $fallbackProfile) {
            if ($fallbackProfile === $profile) {
                continue;
            }

            $resolved = $this->resolveMonthNumberFromProfile($normalized, $fallbackProfile);
            if ($resolved > 0) {
                return $resolved;
            }
        }

        if (!$allowFuzzy) {
            return 0;
        }

        $map = $profile->monthMap();
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

    private function resolveMonthNumberFromProfile(string $normalized, RoadmapLocaleProfile $profile): int
    {
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

    private function looksLikeDateContinuationFragment(string $line): bool
    {
        return 1 === preg_match(
            '/^(?:[•\*\-\.,]?\s*)?(?:\d{1,2}\b|(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC|JANV|FEV|FÉV|MARS|AVR|MAI|JUIN|JUIL|AOUT|AOÛT|SEPT|OCT|NOV|DEC|D[ÉE]C|JANUAR|FEBRUAR|MARZ|MÄRZ|APRIL|MAI|JUNI|JULI|AUGUST|SEPTEMBER|OKTOBER|NOVEMBER|DEZEMBER)\b|(?:BIS|TO)\b)/iu',
            $line,
        );
    }

    private function normalizeTitle(string $value): string
    {
        $cleaned = $this->normalizeOcrUnicodeArtifacts($value);
        $title = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);
        $title = preg_replace('/^[\s•·]+/u', '', $title) ?? $title;
        $title = preg_replace('/\s+([,.;:!?])/u', '$1', $title) ?? $title;
        $title = preg_replace('/(?:\s+00)+\s*$/u', '', $title) ?? $title;
        $title = preg_replace('/\s*[•·]+\s*$/u', '', $title) ?? $title;
        $title = preg_replace('/\s*[^\p{L}\p{N}\)\]]+\s*$/u', '', $title) ?? $title;
        $title = preg_replace('/\s*(?:©|\(C\)|BETHESDA|ZENIMAX|COMMUNITY\s+CALENDAR).*/iu', '', $title) ?? $title;

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

    private function normalizeParsedYear(string $rawYear, DateTimeImmutable $referenceDate): ?int
    {
        $clean = trim($rawYear);
        if ('' === $clean || !ctype_digit($clean)) {
            return null;
        }

        $parsed = (int) $clean;
        if (2 === strlen($clean)) {
            $baseCentury = (int) floor(((int) $referenceDate->format('Y')) / 100);
            $parsed = ($baseCentury * 100) + $parsed;
        }

        if ($parsed < 2000 || $parsed > 2100) {
            return null;
        }

        return $parsed;
    }

    private function resolveReferenceDateFromRawText(string $rawText, DateTimeImmutable $fallback): DateTimeImmutable
    {
        $yearCounts = $this->countCandidateYears($rawText, true);
        if ([] === $yearCounts) {
            $yearCounts = $this->countCandidateYears($rawText, false);
        }

        if ([] === $yearCounts) {
            return $fallback;
        }

        arsort($yearCounts);
        $topFrequency = max($yearCounts);
        $candidates = [];
        foreach ($yearCounts as $year => $count) {
            if ($count === $topFrequency) {
                $candidates[] = (int) $year;
            }
        }

        if ([] === $candidates) {
            return $fallback;
        }

        $fallbackYear = (int) $fallback->format('Y');
        usort($candidates, static function (int $left, int $right) use ($fallbackYear): int {
            $leftDelta = abs($left - $fallbackYear);
            $rightDelta = abs($right - $fallbackYear);
            if ($leftDelta !== $rightDelta) {
                return $leftDelta <=> $rightDelta;
            }

            return $right <=> $left;
        });
        $selectedYear = $candidates[0];

        return $fallback->setDate(
            $selectedYear,
            (int) $fallback->format('m'),
            (int) $fallback->format('d'),
        );
    }

    /**
     * @return array<int, int>
     */
    private function countCandidateYears(string $rawText, bool $skipNoisyLines): array
    {
        $yearCounts = [];
        $lines = preg_split('/\R/u', $rawText);
        if (!is_array($lines)) {
            return [];
        }

        foreach ($lines as $line) {
            $normalizedLine = $this->normalizeWord((string) $line);
            if (
                $skipNoisyLines
                && (
                    str_contains($normalizedLine, 'BETHESDA')
                    || str_contains($normalizedLine, 'ZENIMAX')
                    || str_contains($normalizedLine, 'COMMUNITY CALENDAR')
                    || 1 === preg_match('/\bTM\b/u', $normalizedLine)
                )
            ) {
                continue;
            }

            if (!preg_match_all('/\b(20\d{2})\b/u', (string) $line, $matches)) {
                continue;
            }
            foreach ($matches[1] as $rawYear) {
                $year = (int) $rawYear;
                if ($year < 2018 || $year > 2035) {
                    continue;
                }
                $yearCounts[$year] = ($yearCounts[$year] ?? 0) + 1;
            }
        }

        return $yearCounts;
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

        $isBrandingLine = 1 === preg_match(
            '/^(?:\d{4}\s+)?(?:COMMUNITY CALENDAR|BETHESDA|ZENIMAX|FALLOUT(?:\s*76)?|FALLEUT(?:\s*76)?)$/u',
            $normalized,
        );
        if ($isBrandingLine || 1 === preg_match('/\bTM\b/u', $normalized)) {
            return true;
        }

        if (str_contains($normalized, 'BETHESDA') && mb_strlen($normalized) <= 20) {
            return true;
        }

        if ('RIP DARING' === $normalized || 'BETHESDA' === $normalized) {
            return true;
        }

        return false;
    }
}
