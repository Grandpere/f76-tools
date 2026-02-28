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

use App\Identity\Application\Security\SignedUrlGenerator;
use App\Identity\Domain\Entity\UserEntity;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ResetPasswordControllerTest extends WebTestCase
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

    public function testCanResetPasswordWithValidToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $user = $this->createUser('reset-target@example.com', 'secret123');
        $user->setResetPasswordTokenHash(hash('sha256', $rawToken));
        $user->setResetPasswordExpiresAt(new DateTimeImmutable()->add(new DateInterval('PT2H')));
        $user->setResetPasswordRequestedAt(new DateTimeImmutable());
        $this->entityManager?->flush();

        $signedUrl = $this->signedUrl('app_reset_password', ['token' => $rawToken]);
        $crawler = $this->browser()->request('GET', $signedUrl);
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('a[href="/login"]'));
        self::assertCount(1, $crawler->filter('a[href^="/forgot-password"]'));
        self::assertCount(1, $crawler->filter('a[href^="/contact"]'));

        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', $signedUrl, [
            '_csrf_token' => $csrfToken,
            'password' => 'new-secret123',
            'password_confirm' => 'new-secret123',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('reset-target@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNull($updated->getResetPasswordTokenHash());
        self::assertNull($updated->getResetPasswordExpiresAt());
        self::assertNull($updated->getResetPasswordRequestedAt());
        self::assertTrue($this->passwordHasher()->isPasswordValid($updated, 'new-secret123'));
    }

    public function testInvalidTokenRedirectsToLogin(): void
    {
        $this->createUser('reset-invalid@example.com', 'secret123');

        $this->browser()->request('GET', $this->signedUrl('app_reset_password', ['token' => 'not-a-valid-token']));

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    public function testExpiredTokenRedirectsToLogin(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $user = $this->createUser('reset-expired@example.com', 'secret123');
        $user->setResetPasswordTokenHash(hash('sha256', $rawToken));
        $user->setResetPasswordExpiresAt(new DateTimeImmutable()->sub(new DateInterval('PT1M')));
        $this->entityManager?->flush();

        $this->browser()->request('GET', $this->signedUrl('app_reset_password', ['token' => $rawToken]));

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
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

    /**
     * @param array<string, scalar> $parameters
     */
    private function signedUrl(string $routeName, array $parameters): string
    {
        $generator = $this->browser()->getContainer()->get(SignedUrlGenerator::class);
        \assert($generator instanceof SignedUrlGenerator);

        return $generator->generate($routeName, $parameters);
    }
}
