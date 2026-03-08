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

use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuditLogControllerTest extends WebTestCase
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

    public function testNonAdminCannotAccessAuditLogsPage(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/en/admin/audit-logs');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testNonAdminCannotExportAuditLogsCsv(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/en/admin/audit-logs/export.csv');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanSeeAuditLogsAndFilterByAction(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', '/en/admin/audit-logs?action=user_toggle_active');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $content = $this->browser()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('user_toggle_active', $content);
        self::assertStringContainsString('admin@example.com', $content);
        self::assertStringContainsString('managed@example.com', $content);
    }

    public function testAdminCanExportAuditLogsCsvWithFilters(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->browser()->request('GET', '/en/admin/audit-logs/export.csv?action=user_toggle_active');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $this->browser()->getResponse()->headers->get('content-type'));

        $content = $this->browser()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('occurred_at,action,actor_email,target_email,context_json', $content);
        self::assertStringContainsString('user_toggle_active', $content);
        self::assertStringContainsString('admin@example.com', $content);
        self::assertStringContainsString('managed@example.com', $content);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $plainPassword, array $roles): UserEntity
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
