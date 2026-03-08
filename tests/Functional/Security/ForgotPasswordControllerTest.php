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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ForgotPasswordControllerTest extends WebTestCase
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

    public function testForgotPasswordPageIsAccessible(): void
    {
        $crawler = $this->browser()->request('GET', '/en/forgot-password');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('a[href^="/en/login"]'));
        self::assertCount(1, $crawler->filter('a[href^="/en/contact"]'));
    }

    public function testForgotPasswordCreatesResetTokenForExistingUser(): void
    {
        $email = sprintf('forgot-target-%s@example.com', uniqid('', true));
        $this->createUser($email, 'secret123');

        $crawler = $this->browser()->request('GET', '/en/forgot-password');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/en/forgot-password', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNotNull($updated->getResetPasswordTokenHash());
        self::assertNotNull($updated->getResetPasswordExpiresAt());
        self::assertNotNull($updated->getResetPasswordRequestedAt());
    }

    public function testForgotPasswordReturnsGenericResponseForUnknownEmail(): void
    {
        $email = sprintf('unknown-%s@example.com', uniqid('', true));
        $crawler = $this->browser()->request('GET', '/en/forgot-password');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/en/forgot-password', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    public function testForgotPasswordIsRateLimitedAfterRepeatedAttempts(): void
    {
        $email = sprintf('ratelimit-forgot-%s@example.com', uniqid('', true));
        $crawler = $this->browser()->request('GET', '/en/forgot-password');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        for ($i = 0; $i < 3; ++$i) {
            $this->browser()->request('POST', '/en/forgot-password', [
                '_csrf_token' => $csrfToken,
                'email' => $email,
            ]);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        }

        $this->browser()->request('POST', '/en/forgot-password', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/forgot-password', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->followRedirect();
        self::assertStringContainsString('Too many attempts', (string) $this->browser()->getResponse()->getContent());
    }

    private function createUser(string $email, string $plainPassword): UserEntity
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER']);
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
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE auth_audit_log, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
