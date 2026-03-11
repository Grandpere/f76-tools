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

final class RoadmapParsedEventsValidator
{
    private const MIN_SEASON_EVENTS = 8;
    private const MIN_PARTIAL_SEASON_EVENTS = 6;
    private const MAX_REASONABLE_EVENTS = 40;
    private const MAX_REASONABLE_EVENT_DAYS = 35;

    /**
     * @param list<RoadmapParsedEvent> $events
     */
    public function validate(array $events, string $locale, string $rawText = ''): RoadmapParsedEventsValidationResult
    {
        $errors = [];
        $warnings = [];

        if ([] === $events) {
            $errors[] = 'No event detected.';

            return new RoadmapParsedEventsValidationResult($errors, $warnings);
        }

        $eventCount = count($events);
        if ($eventCount > self::MAX_REASONABLE_EVENTS) {
            $errors[] = sprintf('Too many events detected (%d).', $eventCount);
        }

        $isSeasonRoadmap = $this->looksLikeSeasonRoadmapText($rawText);
        if ($isSeasonRoadmap) {
            $expectedMinimum = $this->resolveMinimumSeasonEvents($rawText, $events);
            if ($eventCount < $expectedMinimum) {
                $errors[] = sprintf(
                    'Too few events detected for a season roadmap (%d, expected at least %d).',
                    $eventCount,
                    $expectedMinimum,
                );
            }
        }

        /** @var array<string, array{index: int, titleKey: string}> $seenRanges */
        $seenRanges = [];
        $previousStartsAt = null;

        foreach ($events as $index => $event) {
            $labelIndex = $index + 1;
            if ($event->endsAt < $event->startsAt) {
                $errors[] = sprintf('Event #%d has end date before start date.', $labelIndex);
                continue;
            }

            if (null !== $previousStartsAt && $event->startsAt < $previousStartsAt) {
                $errors[] = sprintf('Events are not chronological around #%d.', $labelIndex);
            }
            $previousStartsAt = $event->startsAt;

            $duration = $event->startsAt->diff($event->endsAt);
            $durationDays = $duration->days;
            if (is_int($durationDays) && $durationDays > self::MAX_REASONABLE_EVENT_DAYS) {
                $warnings[] = sprintf('Event #%d duration looks long (%d days).', $labelIndex, $durationDays);
            }

            if (mb_strlen(trim($event->title)) < 4) {
                $warnings[] = sprintf('Event #%d title looks too short.', $labelIndex);
            }

            $rangeKey = $event->startsAt->format('Y-m-d H:i:s').'|'.$event->endsAt->format('Y-m-d H:i:s');
            if (isset($seenRanges[$rangeKey])) {
                if ($seenRanges[$rangeKey]['titleKey'] === $this->normalizeTitleForDuplicateCheck($event->title)) {
                    $warnings[] = sprintf(
                        'Duplicate range detected between events #%d and #%d.',
                        $seenRanges[$rangeKey]['index'],
                        $labelIndex,
                    );
                }
            } else {
                $seenRanges[$rangeKey] = [
                    'index' => $labelIndex,
                    'titleKey' => $this->normalizeTitleForDuplicateCheck($event->title),
                ];
            }
        }

        return new RoadmapParsedEventsValidationResult(
            $this->prefixLocale($errors, $locale),
            $this->prefixLocale($warnings, $locale),
        );
    }

    private function looksLikeSeasonRoadmapText(string $rawText): bool
    {
        if ('' === trim($rawText)) {
            return false;
        }

        return 1 === preg_match('/\b(?:SEASON|SAISON|COMMUNITY\s+CALENDAR|CALENDRIER)\b/iu', $rawText);
    }

    /**
     * @param list<RoadmapParsedEvent> $events
     */
    private function resolveMinimumSeasonEvents(string $rawText, array $events): int
    {
        if ('' === trim($rawText)) {
            return self::MIN_SEASON_EVENTS;
        }

        // Late-season roadmap cards can explicitly announce upcoming months and legitimately contain fewer entries.
        if (1 === preg_match('/\b(?:COMING\s+SOON|WERDEN\s+IN\s+K[ÜU]RZE|BIENT[ÔO]T|A\s+VENIR|TO\s+BE\s+ANNOUNCED)\b/iu', $rawText)) {
            return self::MIN_PARTIAL_SEASON_EVENTS;
        }

        $windowDays = $this->estimateEventWindowDays($events);
        if (is_int($windowDays) && $windowDays <= 50) {
            return self::MIN_PARTIAL_SEASON_EVENTS;
        }

        return self::MIN_SEASON_EVENTS;
    }

    /**
     * @param list<RoadmapParsedEvent> $events
     */
    private function estimateEventWindowDays(array $events): ?int
    {
        if ([] === $events) {
            return null;
        }

        $min = $events[0]->startsAt;
        $max = $events[0]->endsAt;
        foreach ($events as $event) {
            if ($event->startsAt < $min) {
                $min = $event->startsAt;
            }
            if ($event->endsAt > $max) {
                $max = $event->endsAt;
            }
        }

        $diff = $min->diff($max);

        return is_int($diff->days) ? $diff->days : null;
    }

    private function normalizeTitleForDuplicateCheck(string $title): string
    {
        $normalized = mb_strtoupper(trim($title));
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @param list<string> $messages
     *
     * @return list<string>
     */
    private function prefixLocale(array $messages, string $locale): array
    {
        if ([] === $messages) {
            return [];
        }

        $prefix = '['.strtoupper(trim($locale)).'] ';

        return array_map(
            static fn (string $message): string => $prefix.$message,
            $messages,
        );
    }
}
