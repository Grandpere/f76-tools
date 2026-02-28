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

final class RegistrationControllerTest extends WebTestCase
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

    public function testRegisterPageIsAccessible(): void
    {
        $crawler = $this->browser()->request('GET', '/register');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('a[href^="/auth/google/start"]'));
        self::assertCount(1, $crawler->filter('a[href^="/forgot-password"]'));
        self::assertCount(1, $crawler->filter('a[href^="/contact"]'));
    }

    public function testRegisterCreatesUserAndRedirectsToLogin(): void
    {
        $crawler = $this->browser()->request('GET', '/register');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $token = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/register', [
            '_csrf_token' => $token,
            'email' => 'new-user@example.com',
            'password' => 'secret123',
            'password_confirm' => 'secret123',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $user = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => 'new-user@example.com']);
        self::assertInstanceOf(UserEntity::class, $user);
        self::assertFalse($user->isEmailVerified());
        self::assertNotNull($user->getEmailVerificationTokenHash());
        self::assertNotNull($user->getEmailVerificationExpiresAt());
        self::assertNotNull($user->getEmailVerificationRequestedAt());
    }

    public function testRegisterIsRateLimitedAfterRepeatedAttempts(): void
    {
        $email = sprintf('ratelimit-register-%s@example.com', uniqid('', true));
        $crawler = $this->browser()->request('GET', '/register');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $token = (string) $tokenNode->attr('value');

        for ($i = 0; $i < 3; ++$i) {
            $this->browser()->request('POST', '/register', [
                '_csrf_token' => $token,
                'email' => $email,
                'password' => 'secret123',
                'password_confirm' => 'different',
            ]);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        }

        $this->browser()->request('POST', '/register', [
            '_csrf_token' => $token,
            'email' => $email,
            'password' => 'secret123',
            'password_confirm' => 'different',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/register', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->followRedirect();
        self::assertStringContainsString('Too many attempts', (string) $this->browser()->getResponse()->getContent());
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
