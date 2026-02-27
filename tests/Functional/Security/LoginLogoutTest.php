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

final class LoginLogoutTest extends WebTestCase
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

    public function testLoginPageIsAccessibleWhenAnonymous(): void
    {
        $crawler = $this->browser()->request('GET', '/login');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[name="_username"]'));
        self::assertCount(1, $crawler->filter('input[name="_password"]'));
    }

    public function testCanLoginAndLogoutWithFormAuthentication(): void
    {
        $user = $this->createUser('security-login@example.com', 'secret123');

        $crawler = $this->browser()->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => $user->getEmail(),
            '_password' => 'secret123',
        ]);
        $this->browser()->submit($form);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->followRedirect();
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString($user->getEmail(), $this->browser()->getResponse()->getContent() ?: '');

        $logoutForm = $this->browser()->getCrawler()->filter('form[action*="/logout"]')->form();
        $this->browser()->submit($logoutForm);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->request('GET', '/');
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testCannotLoginWhenEmailIsNotVerified(): void
    {
        $user = $this->createUser('security-unverified@example.com', 'secret123', isEmailVerified: false);

        $crawler = $this->browser()->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => $user->getEmail(),
            '_password' => 'secret123',
        ]);
        $this->browser()->submit($form);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->followRedirect();
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $this->browser()->getCrawler()->filter('form'));
        self::assertCount(1, $this->browser()->getCrawler()->filter('input[name="_username"]'));
    }

    public function testGetLogoutDoesNotTerminateSession(): void
    {
        $user = $this->createUser('security-get-logout@example.com', 'secret123');

        $crawler = $this->browser()->request('GET', '/login');
        $form = $crawler->filter('form')->form([
            '_username' => $user->getEmail(),
            '_password' => 'secret123',
        ]);
        $this->browser()->submit($form);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $this->browser()->followRedirect();

        $this->browser()->request('GET', '/logout');

        $this->browser()->request('GET', '/');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString($user->getEmail(), $this->browser()->getResponse()->getContent() ?: '');
    }

    public function testLoginIsRateLimitedAfterRepeatedFailures(): void
    {
        $user = $this->createUser('security-ratelimit@example.com', 'secret123');

        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $crawler = $this->browser()->request('GET', '/login?locale=en');
            $form = $crawler->filter('form')->form([
                '_username' => $user->getEmail(),
                '_password' => 'wrong-password',
            ]);
            $this->browser()->submit($form);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
            $this->browser()->followRedirect();
        }

        $crawler = $this->browser()->request('GET', '/login?locale=en');
        $form = $crawler->filter('form')->form([
            '_username' => $user->getEmail(),
            '_password' => 'secret123',
        ]);
        $this->browser()->submit($form);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    private function createUser(string $email, string $plainPassword, bool $isEmailVerified = true): UserEntity
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_USER'])
            ->setIsEmailVerified($isEmailVerified);
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

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
