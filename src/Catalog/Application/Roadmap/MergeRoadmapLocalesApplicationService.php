<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap;

use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventTranslationEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use DateTimeImmutable;
use RuntimeException;

final readonly class MergeRoadmapLocalesApplicationService
{
    public function __construct(
        private RoadmapSnapshotWriteRepository $snapshotWriteRepository,
        private RoadmapRawTextEventParser $roadmapRawTextEventParser,
        private RoadmapCanonicalEventWriteRepository $roadmapCanonicalEventWriteRepository,
    ) {
    }

    /**
     * @param array{fr: int, en: int, de: int} $snapshotIdsByLocale
     */
    public function merge(array $snapshotIdsByLocale, bool $dryRun = false): MergeRoadmapLocalesResult
    {
        $buckets = [];
        $warnings = [];
        $localeCount = 3;

        foreach ($snapshotIdsByLocale as $locale => $snapshotId) {
            $snapshot = $this->snapshotWriteRepository->findOneById($snapshotId);
            if (null === $snapshot) {
                throw new RuntimeException(sprintf('Roadmap snapshot not found: %d', $snapshotId));
            }
            $this->assertApprovedSnapshot($snapshot, (string) $locale);

            $parsedEvents = $this->roadmapRawTextEventParser->parse(
                $snapshot->getRawText(),
                $locale,
                $snapshot->getScannedAt(),
            );

            foreach ($parsedEvents as $event) {
                $key = $this->rangeKey($event->startsAt, $event->endsAt);
                if (!isset($buckets[$key])) {
                    $buckets[$key] = [
                        'startsAt' => $event->startsAt,
                        'endsAt' => $event->endsAt,
                        'titles' => [],
                    ];
                }

                if (!isset($buckets[$key]['titles'][$locale])) {
                    $buckets[$key]['titles'][$locale] = $event->title;
                }
            }
        }

        usort($buckets, static function (array $a, array $b): int {
            $starts = $a['startsAt'] <=> $b['startsAt'];
            if (0 !== $starts) {
                return $starts;
            }

            return $a['endsAt'] <=> $b['endsAt'];
        });

        $canonicalEvents = [];
        $high = 0;
        $medium = 0;
        $low = 0;

        foreach ($buckets as $index => $bucket) {
            $titleLocales = array_keys($bucket['titles']);
            $confidence = (int) round((count($titleLocales) / $localeCount) * 100);
            if ($confidence >= 100) {
                ++$high;
            } elseif ($confidence >= 67) {
                ++$medium;
            } else {
                ++$low;
            }

            if (count($titleLocales) < $localeCount) {
                $warnings[] = sprintf(
                    'Missing locale titles for range %s -> %s (%s)',
                    $bucket['startsAt']->format('Y-m-d'),
                    $bucket['endsAt']->format('Y-m-d'),
                    implode(', ', $titleLocales),
                );
            }

            $canonicalEvent = (new RoadmapCanonicalEventEntity())
                ->setTranslationKey(sprintf('roadmap.event.%s.%s', $bucket['startsAt']->format('Ymd'), $bucket['endsAt']->format('Ymd')))
                ->setStartsAt($bucket['startsAt'])
                ->setEndsAt($bucket['endsAt'])
                ->setSortOrder($index + 1)
                ->setConfidenceScore($confidence);

            foreach ($bucket['titles'] as $locale => $title) {
                $canonicalEvent->addTranslation(
                    (new RoadmapCanonicalEventTranslationEntity())
                        ->setLocale((string) $locale)
                        ->setTitle((string) $title),
                );
            }

            $canonicalEvents[] = $canonicalEvent;
        }

        $warnings = array_merge($warnings, $this->detectPotentialRangeConflicts($buckets, $localeCount));

        if (!$dryRun) {
            $this->roadmapCanonicalEventWriteRepository->clearAll();
            $this->roadmapCanonicalEventWriteRepository->saveAll($canonicalEvents);
        }

        return new MergeRoadmapLocalesResult(
            totalEvents: count($canonicalEvents),
            highConfidenceEvents: $high,
            mediumConfidenceEvents: $medium,
            lowConfidenceEvents: $low,
            warnings: $warnings,
        );
    }

    private function rangeKey(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt): string
    {
        return $startsAt->format('Y-m-d H:i:s').'|'.$endsAt->format('Y-m-d H:i:s');
    }

    private function assertApprovedSnapshot(RoadmapSnapshotEntity $snapshot, string $locale): void
    {
        if (RoadmapSnapshotStatusEnum::APPROVED === $snapshot->getStatus()) {
            return;
        }

        $snapshotId = $snapshot->getId();
        $id = is_int($snapshotId) ? (string) $snapshotId : 'unknown';

        throw new RuntimeException(sprintf(
            'Snapshot %s for locale %s must be approved before merge (current: %s).',
            $id,
            strtoupper($locale),
            $snapshot->getStatus()->value,
        ));
    }

    /**
     * @param list<array{startsAt: DateTimeImmutable, endsAt: DateTimeImmutable, titles: array<string, string>}> $buckets
     * @return list<string>
     */
    private function detectPotentialRangeConflicts(array $buckets, int $localeCount): array
    {
        $warnings = [];
        $count = count($buckets);

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $left = $buckets[$i];
                $right = $buckets[$j];

                if ($left['endsAt']->format('Y-m-d') !== $right['endsAt']->format('Y-m-d')) {
                    continue;
                }

                if ($left['startsAt']->format('Y-m-d') === $right['startsAt']->format('Y-m-d')) {
                    continue;
                }

                $leftLocales = array_values(array_unique(array_keys($left['titles'])));
                $rightLocales = array_values(array_unique(array_keys($right['titles'])));
                sort($leftLocales);
                sort($rightLocales);

                $coveredLocales = array_values(array_unique(array_merge($leftLocales, $rightLocales)));
                if (count($coveredLocales) < $localeCount) {
                    continue;
                }

                if ([] !== array_intersect($leftLocales, $rightLocales)) {
                    continue;
                }

                $leftStartDay = (int) $left['startsAt']->format('d');
                $rightStartDay = (int) $right['startsAt']->format('d');
                if (abs($leftStartDay - $rightStartDay) < 7) {
                    continue;
                }

                $warnings[] = sprintf(
                    'Potential OCR day mismatch for end %s: %s starts %s vs %s starts %s.',
                    $left['endsAt']->format('Y-m-d'),
                    implode(',', $leftLocales),
                    $left['startsAt']->format('Y-m-d'),
                    implode(',', $rightLocales),
                    $right['startsAt']->format('Y-m-d'),
                );
            }
        }

        return $warnings;
    }
}
