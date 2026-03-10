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

namespace App\Tests\Integration\Catalog\Roadmap;

use App\Catalog\Application\Roadmap\RoadmapCanonicalEventReadRepository;
use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventTranslationEntity;
use App\Catalog\Domain\Entity\RoadmapEventEntity;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RoadmapSeasonedCanonicalRepositoryIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?RoadmapCanonicalEventReadRepository $canonicalReadRepository = null;
    private ?RoadmapSeasonRepository $seasonRepository = null;
    private ?RoadmapSnapshotWriteRepository $snapshotRepository = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $canonicalReadRepository = $container->get(RoadmapCanonicalEventReadRepository::class);
        \assert($canonicalReadRepository instanceof RoadmapCanonicalEventReadRepository);
        $this->canonicalReadRepository = $canonicalReadRepository;

        $seasonRepository = $container->get(RoadmapSeasonRepository::class);
        \assert($seasonRepository instanceof RoadmapSeasonRepository);
        $this->seasonRepository = $seasonRepository;

        $snapshotRepository = $container->get(RoadmapSnapshotWriteRepository::class);
        \assert($snapshotRepository instanceof RoadmapSnapshotWriteRepository);
        $this->snapshotRepository = $snapshotRepository;

        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->canonicalReadRepository = null;
        $this->seasonRepository = null;
        $this->snapshotRepository = null;
    }

    public function testReadRepositoryCanScopeCanonicalEventsByActiveSeason(): void
    {
        $season24 = new RoadmapSeasonEntity()
            ->setSeasonNumber(24)
            ->setTitle('Season 24')
            ->setIsActive(true);
        $season25 = new RoadmapSeasonEntity()
            ->setSeasonNumber(25)
            ->setTitle('Season 25')
            ->setIsActive(false);

        $this->entityManager?->persist($season24);
        $this->entityManager?->persist($season25);

        $event24 = new RoadmapCanonicalEventEntity()
            ->setSeason($season24)
            ->setTranslationKey('roadmap.season_24.event.20260303.20260310')
            ->setStartsAt(new DateTimeImmutable('2026-03-03 00:00:00'))
            ->setEndsAt(new DateTimeImmutable('2026-03-10 23:59:59'))
            ->setSortOrder(1)
            ->setConfidenceScore(100);
        $event24->addTranslation(new RoadmapCanonicalEventTranslationEntity()->setLocale('en')->setTitle("BIGFOOT'S BASH"));

        $event25 = new RoadmapCanonicalEventEntity()
            ->setSeason($season25)
            ->setTranslationKey('roadmap.season_25.event.20260604.20260608')
            ->setStartsAt(new DateTimeImmutable('2026-06-04 00:00:00'))
            ->setEndsAt(new DateTimeImmutable('2026-06-08 23:59:59'))
            ->setSortOrder(1)
            ->setConfidenceScore(100);
        $event25->addTranslation(new RoadmapCanonicalEventTranslationEntity()->setLocale('en')->setTitle('CAPS-A-PLENTY'));

        $this->entityManager?->persist($event24);
        $this->entityManager?->persist($event25);
        $this->entityManager?->flush();

        $active = $this->seasonRepository?->findActive();
        self::assertSame(24, $active?->getSeasonNumber());

        $rows = $this->canonicalReadRepository?->findAllOrdered($active);
        self::assertIsArray($rows);
        self::assertCount(1, $rows);
        self::assertSame('roadmap.season_24.event.20260303.20260310', $rows[0]->getTranslationKey());
    }

    public function testFindOneWithEventsByIdReturnsAllSnapshotEvents(): void
    {
        $snapshot = new RoadmapSnapshotEntity()
            ->setLocale('fr')
            ->setSourceImagePath('/tmp/roadmap-fr.jpg')
            ->setSourceImageHash(str_repeat('a', 64))
            ->setOcrProvider('ocr.space')
            ->setOcrConfidence(0.91)
            ->setRawText('SAISON 24')
            ->setScannedAt(new DateTimeImmutable('2026-03-03 10:00:00'));

        $snapshot
            ->addEvent(
                new RoadmapEventEntity()
                    ->setLocale('fr')
                    ->setTitle('Event 1')
                    ->setStartsAt(new DateTimeImmutable('2026-03-03 00:00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-03 23:59:59'))
                    ->setSortOrder(1),
            )
            ->addEvent(
                new RoadmapEventEntity()
                    ->setLocale('fr')
                    ->setTitle('Event 2')
                    ->setStartsAt(new DateTimeImmutable('2026-03-04 00:00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-04 23:59:59'))
                    ->setSortOrder(2),
            )
            ->addEvent(
                new RoadmapEventEntity()
                    ->setLocale('fr')
                    ->setTitle('Event 3')
                    ->setStartsAt(new DateTimeImmutable('2026-03-05 00:00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-05 23:59:59'))
                    ->setSortOrder(3),
            );

        $this->entityManager?->persist($snapshot);
        $this->entityManager?->flush();

        $snapshotId = $snapshot->getId();
        self::assertIsInt($snapshotId);

        $loaded = $this->snapshotRepository?->findOneWithEventsById($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $loaded);
        self::assertCount(3, $loaded->getEvents());
    }

    private function truncateTables(): void
    {
        $this->entityManager?->getConnection()->executeStatement('TRUNCATE TABLE roadmap_canonical_event_translation, roadmap_canonical_event, roadmap_event, roadmap_snapshot, roadmap_season RESTART IDENTITY CASCADE');
    }
}
