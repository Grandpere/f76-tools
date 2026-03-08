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

namespace App\Tests\Functional\Admin;

use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
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
        $this->browser()->request('GET', '/en/admin/roadmap');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessRoadmapPageAndSeeSnapshot(): void
    {
        $admin = $this->createUser('admin-roadmap@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $this->browser()->loginUser($admin);

        $crawler = $this->getAndFollowRedirect('/en/admin/roadmap?snapshot='.$snapshot->getId());

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Roadmap")'));
        self::assertCount(1, $crawler->filter('h2:contains("Canonical timeline")'));
        self::assertCount(3, $crawler->filter('table.translations-table'));
    }

    public function testAdminCanParseAndApproveSnapshot(): void
    {
        $admin = $this->createUser('admin-roadmap-parse@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $snapshotId = $snapshot->getId();
        self::assertNotNull($snapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->getAndFollowRedirect('/en/admin/roadmap?snapshot='.$snapshotId);

        $parseTokenNode = $crawler->filter('form[action*="/en/admin/roadmap/'.$snapshotId.'/raw-text/save"] input[name="_csrf_token"]');
        self::assertCount(1, $parseTokenNode);
        $this->browser()->request('POST', '/en/admin/roadmap/'.$snapshotId.'/raw-text/save', [
            '_csrf_token' => (string) $parseTokenNode->attr('value'),
            'raw_text' => "3 MARS - 10 MARS\nLA FETE DU YETI",
            'generate_events' => '1',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $reloaded = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $reloaded);
        self::assertGreaterThan(0, $reloaded->getEvents()->count());

        $approvePage = $this->getAndFollowRedirect('/en/admin/roadmap?snapshot='.$snapshotId);
        $approveTokenNode = $approvePage->filter('form[action*="/en/admin/roadmap/'.$snapshotId.'/approve"] input[name="_csrf_token"]');
        self::assertCount(1, $approveTokenNode);
        $this->browser()->request('POST', '/en/admin/roadmap/'.$snapshotId.'/approve', [
            '_csrf_token' => (string) $approveTokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $approved = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $approved);
        self::assertSame(RoadmapSnapshotStatusEnum::APPROVED, $approved->getStatus());
        self::assertNotNull($approved->getApprovedAt());
    }

    public function testAdminCanEditGeneratedEvents(): void
    {
        $admin = $this->createUser('admin-roadmap-edit@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('en', "7 APRIL - 14 APRIL\nDOUBLE XP");
        $event = new RoadmapEventEntity()
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
        $crawler = $this->getAndFollowRedirect('/en/admin/roadmap?snapshot='.$snapshotId);
        $tokenNode = $crawler->filter('form[action*="/en/admin/roadmap/'.$snapshotId.'/events/save"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/en/admin/roadmap/'.$snapshotId.'/events/save', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'events' => [
                (string) $eventId => [
                    'title' => 'DOUBLE XP & DOUBLE SCORE',
                    'startsAt' => '2026-04-08T00:00',
                    'endsAt' => '2026-04-15T23:59',
                ],
            ],
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $updatedEvent = $this->entityManager?->getRepository(RoadmapEventEntity::class)->find($eventId);
        self::assertInstanceOf(RoadmapEventEntity::class, $updatedEvent);
        self::assertSame('DOUBLE XP & DOUBLE SCORE', $updatedEvent->getTitle());
    }

    public function testAdminCanSaveRawTextFromReviewForm(): void
    {
        $admin = $this->createUser('admin-roadmap-raw@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $snapshotId = $snapshot->getId();
        self::assertNotNull($snapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->getAndFollowRedirect('/en/admin/roadmap?snapshot='.$snapshotId);
        $tokenNode = $crawler->filter('form[action*="/en/admin/roadmap/'.$snapshotId.'/raw-text/save"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/en/admin/roadmap/'.$snapshotId.'/raw-text/save', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'raw_text' => "3 MARS - 10 MARS\nLA FETE DU YETI\nLIGNE AJOUTEE",
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updatedSnapshot = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertInstanceOf(RoadmapSnapshotEntity::class, $updatedSnapshot);
        self::assertStringContainsString('LIGNE AJOUTEE', $updatedSnapshot->getRawText());
    }

    public function testAdminCanDeleteSnapshotRegardlessOfStatus(): void
    {
        $admin = $this->createUser('admin-roadmap-delete@example.com', ['ROLE_ADMIN']);
        $snapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $snapshot->setStatus(RoadmapSnapshotStatusEnum::APPROVED);
        $this->entityManager?->persist($snapshot);
        $this->entityManager?->flush();

        $snapshotId = $snapshot->getId();
        self::assertNotNull($snapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/en/admin/roadmap');
        $tokenNode = $crawler->filter('form[action*="/en/admin/roadmap/'.$snapshotId.'/delete"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/en/admin/roadmap/'.$snapshotId.'/delete', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $deleted = $this->entityManager?->getRepository(RoadmapSnapshotEntity::class)->find($snapshotId);
        self::assertNull($deleted);
    }

    public function testAdminCanMergeLocalesFromForm(): void
    {
        $admin = $this->createUser('admin-roadmap-merge@example.com', ['ROLE_ADMIN']);
        $frSnapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $enSnapshot = $this->createSnapshot('en', "MARCH 3 - MARCH 10\nBIGFOOT'S BASH");
        $deSnapshot = $this->createSnapshot('de', "3. BIS 10. MÄRZ\nBIGFOOTS PARTY");
        $frSnapshot->setStatus(RoadmapSnapshotStatusEnum::APPROVED);
        $enSnapshot->setStatus(RoadmapSnapshotStatusEnum::APPROVED);
        $deSnapshot->setStatus(RoadmapSnapshotStatusEnum::APPROVED);
        $this->entityManager?->persist($frSnapshot);
        $this->entityManager?->persist($enSnapshot);
        $this->entityManager?->persist($deSnapshot);
        $this->entityManager?->flush();
        $frSnapshotId = $frSnapshot->getId();
        $enSnapshotId = $enSnapshot->getId();
        $deSnapshotId = $deSnapshot->getId();
        self::assertNotNull($frSnapshotId);
        self::assertNotNull($enSnapshotId);
        self::assertNotNull($deSnapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/en/admin/roadmap');
        $tokenNode = $crawler->filter('form[action*="/en/admin/roadmap/merge-locales"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/en/admin/roadmap/merge-locales', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'fr_snapshot_id' => (string) $frSnapshotId,
            'en_snapshot_id' => (string) $enSnapshotId,
            'de_snapshot_id' => (string) $deSnapshotId,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $canonicalEvents = $this->entityManager?->getRepository(RoadmapCanonicalEventEntity::class)->findAll();
        self::assertIsArray($canonicalEvents);
        self::assertGreaterThan(0, count($canonicalEvents));
    }

    public function testAdminMergeLocalesRejectsDraftSnapshots(): void
    {
        $admin = $this->createUser('admin-roadmap-merge-draft@example.com', ['ROLE_ADMIN']);
        $frSnapshot = $this->createSnapshot('fr', "3 MARS - 10 MARS\nLA FETE DU YETI");
        $enSnapshot = $this->createSnapshot('en', "MARCH 3 - MARCH 10\nBIGFOOT'S BASH");
        $deSnapshot = $this->createSnapshot('de', "3. BIS 10. MÄRZ\nBIGFOOTS PARTY");
        $frSnapshotId = $frSnapshot->getId();
        $enSnapshotId = $enSnapshot->getId();
        $deSnapshotId = $deSnapshot->getId();
        self::assertNotNull($frSnapshotId);
        self::assertNotNull($enSnapshotId);
        self::assertNotNull($deSnapshotId);

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/en/admin/roadmap');
        $tokenNode = $crawler->filter('form[action*="/en/admin/roadmap/merge-locales"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/en/admin/roadmap/merge-locales', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'fr_snapshot_id' => (string) $frSnapshotId,
            'en_snapshot_id' => (string) $enSnapshotId,
            'de_snapshot_id' => (string) $deSnapshotId,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $canonicalEvents = $this->entityManager?->getRepository(RoadmapCanonicalEventEntity::class)->findAll();
        self::assertIsArray($canonicalEvents);
        self::assertCount(0, $canonicalEvents);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createSnapshot(string $locale, string $rawText): RoadmapSnapshotEntity
    {
        $snapshot = new RoadmapSnapshotEntity()
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
        $connection->executeStatement('TRUNCATE TABLE roadmap_canonical_event_translation, roadmap_canonical_event, roadmap_event, roadmap_snapshot, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }

    private function getAndFollowRedirect(string $uri): \Symfony\Component\DomCrawler\Crawler
    {
        $crawler = $this->browser()->request('GET', $uri);
        if (302 === $this->browser()->getResponse()->getStatusCode()) {
            return $this->browser()->followRedirect();
        }

        return $crawler;
    }
}
