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
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ChangePasswordControllerTest extends WebTestCase
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

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->browser()->request('GET', '/change-password');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    public function testAuthenticatedUserCanChangePassword(): void
    {
        $user = $this->createUser('change-password@example.com', 'secret123');
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/change-password');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('input[name="current_password"]'));
        self::assertCount(1, $crawler->filter('input[name="new_password"]'));
        self::assertCount(1, $crawler->filter('input[name="new_password_confirm"]'));
        $csrfToken = (string) $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->browser()->request('POST', '/change-password', [
            '_csrf_token' => $csrfToken,
            'current_password' => 'secret123',
            'new_password' => 'new-secret123',
            'new_password_confirm' => 'new-secret123',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/change-password', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('change-password@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertTrue($this->passwordHasher()->isPasswordValid($updated, 'new-secret123'));
        self::assertNull($updated->getResetPasswordTokenHash());
        self::assertNull($updated->getResetPasswordExpiresAt());
        self::assertNull($updated->getResetPasswordRequestedAt());
    }

    public function testChangePasswordIsRejectedWhenCurrentPasswordIsInvalid(): void
    {
        $user = $this->createUser('change-password-invalid@example.com', 'secret123');
        $user->setResetPasswordTokenHash(hash('sha256', 'token'));
        $user->setResetPasswordExpiresAt(new DateTimeImmutable()->add(new DateInterval('PT1H')));
        $user->setResetPasswordRequestedAt(new DateTimeImmutable());
        $this->entityManager?->flush();
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/change-password');
        $csrfToken = (string) $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $this->browser()->request('POST', '/change-password', [
            '_csrf_token' => $csrfToken,
            'current_password' => 'wrong-password',
            'new_password' => 'new-secret123',
            'new_password_confirm' => 'new-secret123',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/change-password', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('change-password-invalid@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertTrue($this->passwordHasher()->isPasswordValid($updated, 'secret123'));
        self::assertNotNull($updated->getResetPasswordTokenHash());
    }

    private function createUser(string $email, string $plainPassword): UserEntity
    {
        $hasher = $this->passwordHasher();

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER']);
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

    private function passwordHasher(): UserPasswordHasherInterface
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        return $hasher;
    }
}
