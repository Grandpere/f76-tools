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
use RuntimeException;

final readonly class GenerateRoadmapEventsFromSnapshotApplicationService
{
    public function __construct(
        private RoadmapSnapshotWriteRepository $snapshotWriteRepository,
        private RoadmapRawTextEventParser $roadmapRawTextEventParser,
    ) {
    }

    /**
     * @return list<RoadmapParsedEvent>
     */
    public function generate(int $snapshotId, bool $dryRun = false): array
    {
        $snapshot = $this->snapshotWriteRepository->findOneById($snapshotId);
        if (null === $snapshot) {
            throw new RuntimeException(sprintf('Roadmap snapshot not found: %d', $snapshotId));
        }

        $parsedEvents = $this->roadmapRawTextEventParser->parse(
            $snapshot->getRawText(),
            $snapshot->getLocale(),
            $snapshot->getScannedAt(),
        );
        usort($parsedEvents, static function (RoadmapParsedEvent $a, RoadmapParsedEvent $b): int {
            $startsAt = $a->startsAt <=> $b->startsAt;
            if (0 !== $startsAt) {
                return $startsAt;
            }

            $endsAt = $a->endsAt <=> $b->endsAt;
            if (0 !== $endsAt) {
                return $endsAt;
            }

            return strcmp($a->title, $b->title);
        });

        if ($dryRun) {
            return $parsedEvents;
        }

        $snapshot->clearEvents();
        foreach ($parsedEvents as $index => $parsedEvent) {
            $snapshot->addEvent(
                new RoadmapEventEntity()
                    ->setLocale($snapshot->getLocale())
                    ->setTitle($parsedEvent->title)
                    ->setStartsAt($parsedEvent->startsAt)
                    ->setEndsAt($parsedEvent->endsAt)
                    ->setSortOrder($index + 1),
            );
        }

        $this->snapshotWriteRepository->save($snapshot);

        return $parsedEvents;
    }
}
