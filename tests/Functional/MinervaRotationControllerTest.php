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

namespace App\Tests\Functional;

use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MinervaRotationControllerTest extends WebTestCase
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
        $this->browser()->request('GET', '/minerva-rotation');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testPageRendersRotationRowsForAuthenticatedUser(): void
    {
        $user = $this->createUser('minerva@example.com');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->createRotation(
            'Foundation',
            7,
            $now->modify('-1 day')->format(DATE_ATOM),
            $now->modify('+1 day')->format(DATE_ATOM),
            MinervaRotationSourceEnum::MANUAL,
        );
        $this->createRotation(
            'Crater',
            8,
            $now->modify('+2 days')->format(DATE_ATOM),
            $now->modify('+4 days')->format(DATE_ATOM),
            MinervaRotationSourceEnum::GENERATED,
        );

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/minerva-rotation');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('.minerva-window-grid'));
        self::assertCount(1, $crawler->filter('[data-controller="minerva-countdown"]'));
        self::assertCount(1, $crawler->filter('[data-minerva-countdown-target-date-value]'));
        self::assertCount(4, $crawler->filter('.minerva-countdown-value'));
        self::assertCount(1, $crawler->filter('[data-controller~="minerva-progression"]'));
        self::assertGreaterThanOrEqual(1, $crawler->filter('[data-minerva-progression-target="cell"][data-list-cycle]')->count());
        self::assertStringContainsString('Foundation', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('table.translations-table'));
        self::assertStringContainsString('Crater', (string) $this->browser()->getResponse()->getContent());
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function createRotation(
        string $location,
        int $listCycle,
        string $startsAt,
        string $endsAt,
        MinervaRotationSourceEnum $source,
    ): void {
        $rotation = new MinervaRotationEntity()
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt))
            ->setSource($source);

        $this->entityManager?->persist($rotation);
        $this->entityManager?->flush();
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation, contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
