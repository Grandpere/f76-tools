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

use App\Entity\AdminAuditLogEntity;
use App\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserManagementControllerTest extends WebTestCase
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

    public function testNonAdminCannotAccessAdminUsersPage(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/admin/users');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanToggleExistingUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        self::assertTrue($managed->isActive());
        $crawler = $this->browser()->request('GET', '/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertFalse($updated->isActive());

        $audit = $this->findAuditForTargetAction('managed@example.com', 'user_toggle_active');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertIsArray($audit->getContext());
        self::assertSame(false, $audit->getContext()['isActive'] ?? null);
    }

    public function testAdminCanGenerateResetLinkForExistingUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/admin/users/%d/generate-reset-link"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/admin/users/%d/generate-reset-link', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNotNull($updated->getResetPasswordTokenHash());
        self::assertNotNull($updated->getResetPasswordExpiresAt());

        $audit = $this->findAuditForTargetAction('managed@example.com', 'user_generate_reset_link');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertIsArray($audit->getContext());
        self::assertIsString($audit->getContext()['expiresAt'] ?? null);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $plainPassword, array $roles): UserEntity
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = (new UserEntity())
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function findUserByEmail(string $email): ?UserEntity
    {
        $result = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        return $result instanceof UserEntity ? $result : null;
    }

    private function findAuditForTargetAction(string $targetEmail, string $action): ?AdminAuditLogEntity
    {
        $result = $this->entityManager?->getRepository(AdminAuditLogEntity::class)->createQueryBuilder('a')
            ->join('a.targetUser', 'u')
            ->where('u.email = :email')
            ->andWhere('a.action = :action')
            ->setParameter('email', $targetEmail)
            ->setParameter('action', $action)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof AdminAuditLogEntity ? $result : null;
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
            throw new \LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
