<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventTranslationEntity;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RoadmapCalendarControllerTest extends WebTestCase
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

    public function testPageRedirectsWhenNotAuthenticated(): void
    {
        $this->browser()->request('GET', '/roadmap-calendar');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testPageRendersCanonicalEventsWithoutTimeDisplay(): void
    {
        $user = $this->createUser('roadmap-calendar@example.com');
        $event = (new RoadmapCanonicalEventEntity())
            ->setTranslationKey('roadmap.event.20260303.20260310')
            ->setStartsAt(new DateTimeImmutable('2026-03-03 00:00:00'))
            ->setEndsAt(new DateTimeImmutable('2026-03-10 23:59:59'))
            ->setSortOrder(1)
            ->setConfidenceScore(100);
        $event->addTranslation(
            (new RoadmapCanonicalEventTranslationEntity())
                ->setLocale('en')
                ->setTitle("BIGFOOT'S BASH"),
        );
        $this->entityManager?->persist($event);
        $this->entityManager?->flush();

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/roadmap-calendar');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.roadmap-calendar-flow'));
        self::assertCount(1, $crawler->filter('.roadmap-calendar-event'));
        self::assertStringContainsString('03/03/2026', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('10/03/2026', (string) $this->browser()->getResponse()->getContent());
        self::assertStringNotContainsString('00:00', (string) $this->browser()->getResponse()->getContent());
        self::assertSame("BIGFOOT'S BASH", trim($crawler->filter('.roadmap-calendar-event-title')->text()));
    }

    private function createUser(string $email): UserEntity
    {
        $user = (new UserEntity())
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE roadmap_canonical_event_translation, roadmap_canonical_event, minerva_rotation, contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
