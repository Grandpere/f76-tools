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

use App\Entity\MinervaRotationEntity;
use App\Entity\UserEntity;
use DateTimeImmutable;
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
        $this->createRotation('Foundation', 7, '2026-03-02T10:00:00+00:00', '2026-03-04T10:00:00+00:00');
        $this->createRotation('Crater', 8, '2026-03-06T10:00:00+00:00', '2026-03-08T10:00:00+00:00');

        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/minerva-rotation');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('table.translations-table'));
        self::assertStringContainsString('Foundation', (string) $this->browser()->getResponse()->getContent());
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

    private function createRotation(string $location, int $listCycle, string $startsAt, string $endsAt): void
    {
        $rotation = new MinervaRotationEntity()
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt));

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
