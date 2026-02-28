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

use App\Identity\Application\Security\ActiveUserSessionRegistry;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use DateTimeImmutable;
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
        self::assertCount(1, $crawler->filter('.auth-actions-inline .auth-button-link-secondary'));
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

    public function testAuthenticatedUserCanUnlinkGoogleIdentityWhenLocalPasswordEnabled(): void
    {
        $user = $this->createUser('security-unlink@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($user, 'sub-security-unlink');
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/account-security');
        $tokenNode = $crawler->filter('form[action*="/account-security/unlink-google"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/account-security/unlink-google', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringEndsWith('/account-security', (string) parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->entityManager?->clear();
        $reloaded = $this->findUserByEmail('security-unlink@example.com');
        self::assertInstanceOf(UserEntity::class, $reloaded);
        $identity = $this->entityManager?->getRepository(UserIdentityEntity::class)->findOneBy([
            'user' => $reloaded,
            'provider' => 'google',
        ]);
        self::assertNull($identity);
    }

    public function testUnlinkGoogleIdentityIsBlockedWithoutLocalPassword(): void
    {
        $user = $this->createUser('security-unlink-blocked@example.com', 'secret123', ['ROLE_USER']);
        $user->setHasLocalPassword(false);
        $this->entityManager?->flush();
        $this->linkGoogleIdentity($user, 'sub-security-unlink-blocked');
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/account-security');
        $tokenNode = $crawler->filter('form[action*="/account-security/unlink-google"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        self::assertCount(1, $crawler->filter('form[action*="/account-security/unlink-google"] button[disabled]'));

        $this->browser()->request('POST', '/account-security/unlink-google', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $reloaded = $this->findUserByEmail('security-unlink-blocked@example.com');
        self::assertInstanceOf(UserEntity::class, $reloaded);
        $identity = $this->entityManager?->getRepository(UserIdentityEntity::class)->findOneBy([
            'user' => $reloaded,
            'provider' => 'google',
        ]);
        self::assertInstanceOf(UserIdentityEntity::class, $identity);
    }

    public function testCanLogoutOtherSessionsFromSecurityPage(): void
    {
        $user = $this->createUser('security-sessions@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);

        $sessionRegistry = $this->browser()->getContainer()->get(ActiveUserSessionRegistry::class);
        \assert($sessionRegistry instanceof ActiveUserSessionRegistry);
        $sessionRegistry->registerOrTouch(
            userId: (int) $user->getId(),
            sessionId: 'other-session-token',
            ipAddress: '198.51.100.7',
            userAgent: 'Mozilla/5.0',
            now: new DateTimeImmutable('-3 minutes'),
        );

        $crawler = $this->browser()->request('GET', '/account-security');
        self::assertCount(1, $crawler->filter('form[action*="/account-security/logout-other-sessions"] input[name="_csrf_token"]'));

        $this->browser()->request('POST', '/account-security/logout-other-sessions', [
            '_csrf_token' => (string) $crawler->filter('form[action*="/account-security/logout-other-sessions"] input[name="_csrf_token"]')->attr('value'),
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $sessions = $sessionRegistry->listSessions((int) $user->getId());
        self::assertCount(1, $sessions);
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

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE auth_audit_log, user_identity, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function linkGoogleIdentity(UserEntity $user, string $providerUserId): void
    {
        $identity = new UserIdentityEntity();
        $identity
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId($providerUserId)
            ->setProviderEmail($user->getEmail());
        $this->entityManager?->persist($identity);
        $this->entityManager?->flush();
    }

    private function findUserByEmail(string $email): ?UserEntity
    {
        $user = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        return $user instanceof UserEntity ? $user : null;
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
