<?php

declare(strict_types=1);

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

        if ($dryRun) {
            return $parsedEvents;
        }

        $snapshot->clearEvents();
        foreach ($parsedEvents as $index => $parsedEvent) {
            $snapshot->addEvent(
                (new RoadmapEventEntity())
                    ->setLocale($snapshot->getLocale())
                    ->setTitle($parsedEvent->title)
                    ->setEventType($parsedEvent->eventType)
                    ->setStartsAt($parsedEvent->startsAt)
                    ->setEndsAt($parsedEvent->endsAt)
                    ->setNotes($parsedEvent->notes)
                    ->setSortOrder($index + 1),
            );
        }

        $this->snapshotWriteRepository->save($snapshot);

        return $parsedEvents;
    }
}

