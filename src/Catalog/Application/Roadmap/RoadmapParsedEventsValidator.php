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
            $expectedMinimum = $this->resolveMinimumSeasonEvents($rawText);
            if ($eventCount < $expectedMinimum) {
                $errors[] = sprintf(
                    'Too few events detected for a season roadmap (%d, expected at least %d).',
                    $eventCount,
                    $expectedMinimum,
                );
            }
        }

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
                $warnings[] = sprintf(
                    'Duplicate range detected between events #%d and #%d.',
                    $seenRanges[$rangeKey],
                    $labelIndex,
                );
            } else {
                $seenRanges[$rangeKey] = $labelIndex;
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

    private function resolveMinimumSeasonEvents(string $rawText): int
    {
        if ('' === trim($rawText)) {
            return self::MIN_SEASON_EVENTS;
        }

        // Late-season roadmap cards can explicitly announce upcoming months and legitimately contain fewer entries.
        if (1 === preg_match('/\b(?:COMING\s+SOON|WERDEN\s+IN\s+K[ÜU]RZE|BIENT[ÔO]T|A\s+VENIR|TO\s+BE\s+ANNOUNCED)\b/iu', $rawText)) {
            return self::MIN_PARTIAL_SEASON_EVENTS;
        }

        return self::MIN_SEASON_EVENTS;
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
