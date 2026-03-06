<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Catalog\Domain\Entity\RoadmapEventEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapSnapshotStatusEnum;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoadmapSnapshotControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->client = null;
    }

    public function testNonAdminCannotAccessRoadmapPage(): void
    {
        $user = $this->createUser('member-roadmap@example.com', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/admin/roadmap');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessRoadmapPageAndSeeSnapshot(): void
    {
        $admin = $this->createUser('admin-roadmap@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/roadmap?snapshot='.$snapshot->getId());

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Roadmap")'));
        self::assertCount(1, $crawler->filter('table.translations-table'));
    }

    public function testAdminCanParseAndApproveSnapshot(): void
    {
        $admin = $this->createUser('admin-roadmap-parse@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $snapshotId = $snapshot->getId();
        self::assertNotNull($snapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/admin/roadmap?snapshot='.$snapshotId);

        $parseTokenNode = $crawler->filter('form[action$="/admin/roadmap/'.$snapshotId.'/parse-events"] input[name="_csrf_token"]');
        self::assertCount(1, $parseTokenNode);
        $this->browser()->request('POST', '/admin/roadmap/'.$snapshotId.'/parse-events', [
            '_csrf_token' => (string) $parseTokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $reloaded = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $reloaded);
        self::assertGreaterThan(0, $reloaded->getEvents()->count());

        $approvePage = $this->browser()->request('GET', '/admin/roadmap?snapshot='.$snapshotId);
        $approveTokenNode = $approvePage->filter('form[action$="/admin/roadmap/'.$snapshotId.'/approve"] input[name="_csrf_token"]');
        self::assertCount(1, $approveTokenNode);
        $this->browser()->request('POST', '/admin/roadmap/'.$snapshotId.'/approve', [
            '_csrf_token' => (string) $approveTokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $approved = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $approved);
        self::assertSame(RoadmapSnapshotStatusEnum::APPROVED, $approved->getStatus());
        self::assertNotNull($approved->getApprovedAt());
    }

    public function testAdminCanEditGeneratedEvents(): void
    {
        $admin = $this->createUser('admin-roadmap-edit@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('en', "7 APRIL - 14 APRIL\nDOUBLE XP");
        $event = (new RoadmapEventEntity())
            ->setSnapshot($snapshot)
            ->setLocale('en')
            ->setTitle('DOUBLE XP')
            ->setStartsAt(new DateTimeImmutable('2026-04-07 00:00:00'))
            ->setEndsAt(new DateTimeImmutable('2026-04-14 23:59:59'))
            ->setSortOrder(1);
        $snapshot->addEvent($event);
        $this->entityManager?->persist($snapshot);
        $this->entityManager?->flush();

        $snapshotId = $snapshot->getId();
        $eventId = $event->getId();
        self::assertNotNull($snapshotId);
        self::assertNotNull($eventId);

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/admin/roadmap?snapshot='.$snapshotId);
        $tokenNode = $crawler->filter('form[action$="/admin/roadmap/'.$snapshotId.'/events/save"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/roadmap/'.$snapshotId.'/events/save', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'events' => [
                (string) $eventId => [
                    'title' => 'DOUBLE XP & DOUBLE SCORE',
                    'startsAt' => '2026-04-08T00:00',
                    'endsAt' => '2026-04-15T23:59',
                    'eventType' => 'bonus',
                    'notes' => 'Updated manually',
                ],
            ],
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $updatedEvent = $this->entityManager?->getRepository(RoadmapEventEntity::class)->find($eventId);
        self::assertInstanceOf(RoadmapEventEntity::class, $updatedEvent);
        self::assertSame('DOUBLE XP & DOUBLE SCORE', $updatedEvent->getTitle());
        self::assertSame('bonus', $updatedEvent->getEventType());
        self::assertSame('Updated manually', $updatedEvent->getNotes());
    }

    private function createUser(string $email, array $roles): UserEntity
    {
        $user = (new UserEntity())
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createSnapshot(string $locale, string $rawText): RoadmapSnapshotEntity
    {
        $snapshot = (new RoadmapSnapshotEntity())
            ->setLocale($locale)
            ->setSourceImagePath('/tmp/sample-roadmap-'.$locale.'.jpg')
            ->setSourceImageHash(hash('sha256', $rawText))
            ->setOcrProvider('ocr.space')
            ->setOcrConfidence(0.91)
            ->setRawText($rawText);

        $this->entityManager?->persist($snapshot);
        $this->entityManager?->flush();

        return $snapshot;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE roadmap_event, roadmap_snapshot, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}

