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

use App\Entity\UserEntity;
use App\Identity\Application\Security\SignedUrlGenerator;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VerifyEmailControllerTest extends WebTestCase
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

    public function testCanVerifyEmailWithValidToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $user = new UserEntity()
            ->setEmail('verify-target@example.com')
            ->setRoles(['ROLE_USER'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS')
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash(hash('sha256', $rawToken))
            ->setEmailVerificationExpiresAt(new DateTimeImmutable()->add(new DateInterval('P1D')));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        $this->browser()->request('GET', $this->signedUrl('app_verify_email', [
            'token' => $rawToken,
        ]));

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $updated = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => 'verify-target@example.com']);
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertTrue($updated->isEmailVerified());
        self::assertNull($updated->getEmailVerificationTokenHash());
        self::assertNull($updated->getEmailVerificationExpiresAt());
    }

    public function testInvalidTokenRedirectsToLogin(): void
    {
        $this->browser()->request('GET', '/verify-email/not-a-valid-token');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
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
