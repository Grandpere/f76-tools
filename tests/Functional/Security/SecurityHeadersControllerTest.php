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

namespace App\Tests\Functional\Security;

use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityHeadersControllerTest extends WebTestCase
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

    public function testSensitiveAuthPageHasNoStoreAndRobotsHeaders(): void
    {
        $this->browser()->request('GET', '/en/login');
        $response = $this->browser()->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('0', $response->headers->get('Expires'));
        self::assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }

    public function testAdminPageHasNoStoreAndRobotsHeaders(): void
    {
        $admin = $this->createUser('admin-headers@example.com', ['ROLE_USER', 'ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $this->browser()->request('GET', '/en/admin/users');
        $response = $this->browser()->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('no-cache', $response->headers->get('Pragma'));
        self::assertSame('0', $response->headers->get('Expires'));
        self::assertSame('noindex, nofollow, noarchive', $response->headers->get('X-Robots-Tag'));
    }

    public function testNonSensitivePageDoesNotForceNoStoreOrRobotsHeaders(): void
    {
        $user = $this->createUser('user-headers@example.com');
        $this->browser()->loginUser($user);

        $this->browser()->request('GET', '/en/roadmap-calendar');
        $response = $this->browser()->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertFalse($response->headers->has('X-Robots-Tag'));
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles = ['ROLE_USER']): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles($roles)
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS')
            ->setIsEmailVerified(true);

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE contact_message, minerva_rotation, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
