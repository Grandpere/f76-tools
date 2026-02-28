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
use App\Identity\Domain\Entity\UserIdentityEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountSecurityControllerTest extends WebTestCase
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
        $this->browser()->request('GET', '/account-security');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    public function testAuthenticatedUserSeesSecurityProfile(): void
    {
        $user = $this->createUser('security-profile@example.com', 'secret123', ['ROLE_USER']);
        $user->setIsEmailVerified(true)->setHasLocalPassword(true);
        $this->entityManager?->flush();
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/account-security');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('security-profile@example.com', $this->browser()->getResponse()->getContent() ?: '');
        self::assertCount(1, $crawler->filter('a[href^="/change-password"]'));
        self::assertCount(1, $crawler->filter('a[href^="/progression"]'));
    }

    public function testSecurityProfileShowsGoogleLinkedState(): void
    {
        $user = $this->createUser('security-google@example.com', 'secret123', ['ROLE_USER']);
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId('sub-security-google')
            ->setProviderEmail($user->getEmail());
        $this->entityManager?->persist($identity);
        $this->entityManager?->flush();
        $this->browser()->loginUser($user);

        $this->browser()->request('GET', '/account-security');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('Linked since', $this->browser()->getResponse()->getContent() ?: '');
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

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE user_identity, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
