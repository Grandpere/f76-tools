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

use App\Catalog\Domain\Entity\RoadmapEventEntity;

final class RoadmapTitleComparisonService
{
    /**
     * @param list<RoadmapParsedEvent> $ocrEvents
     * @param list<RoadmapEventEntity> $manualEvents
     *
     * @return array{
     *   total_ocr: int,
     *   total_manual: int,
     *   matched_windows: int,
     *   exact_matches: int,
     *   average_similarity: float,
     *   placeholder_count: int,
     *   short_title_count: int,
     *   unmatched_ocr_windows: int,
     *   window_mode: string,
     *   mismatches: list<array{
     *     window: string,
     *     similarity: float,
     *     ocr_title: string,
     *     manual_title: string
     *   }>
     * }
     */
    public function compareParsedToManual(array $ocrEvents, array $manualEvents): array
    {
        $manualAsParsed = array_map(
            static fn (RoadmapEventEntity $event): RoadmapParsedEvent => new RoadmapParsedEvent(
                $event->getTitle(),
                $event->getStartsAt(),
                $event->getEndsAt(),
            ),
            $manualEvents,
        );

        return $this->compareParsedToParsed($ocrEvents, $manualAsParsed);
    }

    /**
     * @param list<RoadmapParsedEvent> $leftEvents
     * @param list<RoadmapParsedEvent> $rightEvents
     *
     * @return array{
     *   total_ocr: int,
     *   total_manual: int,
     *   matched_windows: int,
     *   exact_matches: int,
     *   average_similarity: float,
     *   placeholder_count: int,
     *   short_title_count: int,
     *   unmatched_ocr_windows: int,
     *   window_mode: string,
     *   mismatches: list<array{
     *     window: string,
     *     similarity: float,
     *     ocr_title: string,
     *     manual_title: string
     *   }>
     * }
     */
    public function compareParsedToParsed(array $leftEvents, array $rightEvents): array
    {
        $strictMatcher = $this->buildWindowMatcher($leftEvents, $rightEvents, false);
        $result = $this->compareWithMatcher($leftEvents, $rightEvents, $strictMatcher);
        if ($result['matched_windows'] > 0) {
            $result['window_mode'] = 'date';

            return $result;
        }

        $monthDayMatcher = $this->buildWindowMatcher($leftEvents, $rightEvents, true);
        $result = $this->compareWithMatcher($leftEvents, $rightEvents, $monthDayMatcher);
        $result['window_mode'] = 'month_day';

        return $result;
    }

    /**
     * @param list<RoadmapParsedEvent>    $ocrEvents
     * @param list<RoadmapParsedEvent>    $manualEvents
     * @param array<string, list<string>> $manualByWindow
     *
     * @return array{
     *   total_ocr: int,
     *   total_manual: int,
     *   matched_windows: int,
     *   exact_matches: int,
     *   average_similarity: float,
     *   placeholder_count: int,
     *   short_title_count: int,
     *   unmatched_ocr_windows: int,
     *   mismatches: list<array{
     *     window: string,
     *     similarity: float,
     *     ocr_title: string,
     *     manual_title: string
     *   }>
     * }
     */
    private function compareWithMatcher(array $ocrEvents, array $manualEvents, array $manualByWindow): array
    {
        $matchedWindows = 0;
        $exactMatches = 0;
        $similaritySum = 0.0;
        $placeholderCount = 0;
        $shortTitleCount = 0;
        $unmatchedOcrWindows = 0;
        $mismatches = [];

        foreach ($ocrEvents as $event) {
            $startsAt = $event->startsAt->format('Y-m-d');
            $endsAt = $event->endsAt->format('Y-m-d');
            $windowKey = $this->windowKey($startsAt, $endsAt);
            if (!isset($manualByWindow[$windowKey])) {
                ++$unmatchedOcrWindows;
                continue;
            }

            ++$matchedWindows;
            $ocrTitle = trim($event->title);
            $manualTitle = $this->pickBestManualTitleForWindow($ocrTitle, $manualByWindow[$windowKey]);
            if ($this->isPlaceholderTitle($ocrTitle)) {
                ++$placeholderCount;
            }
            if ($this->countWords($ocrTitle) <= 1) {
                ++$shortTitleCount;
            }

            $similarity = $this->titleSimilarity($ocrTitle, $manualTitle);
            $similaritySum += $similarity;

            if ($this->normalizeTitle($ocrTitle) === $this->normalizeTitle($manualTitle)) {
                ++$exactMatches;
                continue;
            }

            $mismatches[] = [
                'window' => sprintf('%s -> %s', $startsAt, $endsAt),
                'similarity' => $similarity,
                'ocr_title' => $ocrTitle,
                'manual_title' => $manualTitle,
            ];
        }

        usort(
            $mismatches,
            static fn (array $left, array $right): int => $left['similarity'] <=> $right['similarity'],
        );

        return [
            'total_ocr' => count($ocrEvents),
            'total_manual' => count($manualEvents),
            'matched_windows' => $matchedWindows,
            'exact_matches' => $exactMatches,
            'average_similarity' => $matchedWindows > 0 ? ($similaritySum / $matchedWindows) : 0.0,
            'placeholder_count' => $placeholderCount,
            'short_title_count' => $shortTitleCount,
            'unmatched_ocr_windows' => $unmatchedOcrWindows,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param list<RoadmapParsedEvent> $ocrEvents
     * @param list<RoadmapParsedEvent> $manualEvents
     *
     * @return array<string, list<string>>
     */
    private function buildWindowMatcher(array $ocrEvents, array $manualEvents, bool $monthDayFallback): array
    {
        if (!$monthDayFallback) {
            $manualByWindow = [];
            foreach ($manualEvents as $event) {
                $key = $this->windowKey($event->startsAt->format('Y-m-d'), $event->endsAt->format('Y-m-d'));
                if (!isset($manualByWindow[$key])) {
                    $manualByWindow[$key] = [];
                }
                $manualByWindow[$key][] = trim($event->title);
            }

            return $manualByWindow;
        }

        $manualByWindow = [];
        foreach ($manualEvents as $event) {
            $key = $this->windowKey($event->startsAt->format('m-d'), $event->endsAt->format('m-d'));
            if (!isset($manualByWindow[$key])) {
                $manualByWindow[$key] = [];
            }
            $manualByWindow[$key][] = trim($event->title);
        }

        $mapped = [];
        foreach ($ocrEvents as $event) {
            $strictKey = $this->windowKey($event->startsAt->format('Y-m-d'), $event->endsAt->format('Y-m-d'));
            $monthDayKey = $this->windowKey($event->startsAt->format('m-d'), $event->endsAt->format('m-d'));
            if (!isset($manualByWindow[$monthDayKey])) {
                continue;
            }
            $mapped[$strictKey] = $manualByWindow[$monthDayKey];
        }

        return $mapped;
    }

    private function windowKey(string $startsAt, string $endsAt): string
    {
        return $startsAt.'|'.$endsAt;
    }

    private function isPlaceholderTitle(string $title): bool
    {
        $normalized = $this->normalizeTitle($title);

        return in_array($normalized, ['EVENEMENT A VERIFIER', 'EVENT TO REVIEW', 'EREIGNIS ZU PRUFEN'], true);
    }

    private function countWords(string $title): int
    {
        $count = preg_match_all('/\p{L}+/u', $title);
        if (!is_int($count)) {
            return 0;
        }

        return $count;
    }

    private function titleSimilarity(string $left, string $right): float
    {
        $leftNormalized = $this->normalizeTitle($left);
        $rightNormalized = $this->normalizeTitle($right);
        if ('' === $leftNormalized && '' === $rightNormalized) {
            return 1.0;
        }
        if ('' === $leftNormalized || '' === $rightNormalized) {
            return 0.0;
        }

        $maxLength = max(strlen($leftNormalized), strlen($rightNormalized));
        $distance = levenshtein($leftNormalized, $rightNormalized);
        $score = 1.0 - ($distance / $maxLength);

        return max(0.0, min(1.0, $score));
    }

    /**
     * @param list<string> $manualTitles
     */
    private function pickBestManualTitleForWindow(string $ocrTitle, array $manualTitles): string
    {
        $bestTitle = $manualTitles[0] ?? '';
        $bestScore = -1.0;

        foreach ($manualTitles as $candidate) {
            $score = $this->titleSimilarity($ocrTitle, $candidate);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTitle = $candidate;
            }
        }

        return $bestTitle;
    }

    private function normalizeTitle(string $title): string
    {
        $upper = mb_strtoupper(trim($title));
        $ascii = strtr($upper, [
            'À' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E',
            'È' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'ẞ' => 'SS',
        ]);
        $collapsed = preg_replace('/[^A-Z0-9]+/u', ' ', $ascii) ?? $ascii;

        return trim(preg_replace('/\s+/u', ' ', $collapsed) ?? $collapsed);
    }
}
