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

final class ResendVerificationControllerTest extends WebTestCase
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

    public function testResendPageIsAccessible(): void
    {
        $crawler = $this->browser()->request('GET', '/resend-verification');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('a[href^="/register"]'));
        self::assertCount(1, $crawler->filter('a[href^="/contact"]'));
    }

    public function testResendUpdatesTokenForUnverifiedUser(): void
    {
        $email = sprintf('resend-target-%s@example.com', uniqid('', true));
        $oldToken = hash('sha256', 'old-token');
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash($oldToken)
            ->setEmailVerificationExpiresAt(new DateTimeImmutable()->add(new DateInterval('PT10M')))
            ->setEmailVerificationRequestedAt(new DateTimeImmutable()->sub(new DateInterval('PT2M')));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        $crawler = $this->browser()->request('GET', '/resend-verification');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/resend-verification', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNotSame($oldToken, $updated->getEmailVerificationTokenHash());
        self::assertNotNull($updated->getEmailVerificationExpiresAt());
        self::assertNotNull($updated->getEmailVerificationRequestedAt());
    }

    public function testResendDoesNotUpdateTokenWhenCooldownIsActive(): void
    {
        $email = sprintf('resend-cooldown-%s@example.com', uniqid('', true));
        $oldToken = hash('sha256', 'same-token');
        $requestedAt = new DateTimeImmutable();
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash($oldToken)
            ->setEmailVerificationExpiresAt(new DateTimeImmutable()->add(new DateInterval('PT10M')))
            ->setEmailVerificationRequestedAt($requestedAt);

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        $crawler = $this->browser()->request('GET', '/resend-verification');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/resend-verification', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertSame($oldToken, $updated->getEmailVerificationTokenHash());
        self::assertEquals($requestedAt->getTimestamp(), $updated->getEmailVerificationRequestedAt()?->getTimestamp());
    }

    public function testResendVerificationIsRateLimitedAfterRepeatedAttempts(): void
    {
        $email = sprintf('ratelimit-resend-%s@example.com', uniqid('', true));
        $crawler = $this->browser()->request('GET', '/resend-verification');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        for ($i = 0; $i < 3; ++$i) {
            $this->browser()->request('POST', '/resend-verification', [
                '_csrf_token' => $csrfToken,
                'email' => $email,
            ]);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        }

        $this->browser()->request('POST', '/resend-verification', [
            '_csrf_token' => $csrfToken,
            'email' => $email,
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/resend-verification', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

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
