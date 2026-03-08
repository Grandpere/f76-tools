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

use App\Identity\Domain\Entity\AuthAuditLogEntity;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
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
        $this->browser()->request('GET', '/en/admin/users');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testNonAdminCannotExportUsersCsv(): void
    {
        $user = $this->createUser('member-export@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/en/admin/users/export');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testNonAdminCannotAccessUserAuthEventsPage(): void
    {
        $user = $this->createUser('member-auth-events@example.com', 'secret123', ['ROLE_USER']);
        $target = $this->createUser('target-auth-events@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);

        $this->browser()->request('GET', sprintf('/en/admin/users/%d/auth-events', $target->getId()));

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testNonAdminCannotExportUserAuthEventsCsv(): void
    {
        $user = $this->createUser('member-auth-events-export@example.com', 'secret123', ['ROLE_USER']);
        $target = $this->createUser('target-auth-events-export@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);

        $this->browser()->request('GET', sprintf('/en/admin/users/%d/auth-events/export.csv', $target->getId()));

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanSeeUserAuthEventsPage(): void
    {
        $admin = $this->createUser('admin-auth-events@example.com', 'secret123', ['ROLE_ADMIN']);
        $target = $this->createUser('target-auth-events@example.com', 'secret123', ['ROLE_USER']);
        $authEvent = new AuthAuditLogEntity()
            ->setUser($target)
            ->setLevel('info')
            ->setEvent('security.auth.login.success')
            ->setClientIp('127.0.0.1')
            ->setContext(['scope' => 'login']);
        $this->entityManager?->persist($authEvent);
        $this->entityManager?->flush();

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', sprintf('/en/admin/users/%d/auth-events', $target->getId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('security.auth.login.success', (string) $this->browser()->getResponse()->getContent());
        self::assertStringContainsString('target-auth-events@example.com', (string) $this->browser()->getResponse()->getContent());
        self::assertCount(1, $crawler->filter(sprintf('a[href^="/en/admin/users/%d/auth-events/export.csv"]', $target->getId())));
    }

    public function testAdminCanFilterUserAuthEventsByLevelAndQuery(): void
    {
        $admin = $this->createUser('admin-auth-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $target = $this->createUser('target-auth-filter@example.com', 'secret123', ['ROLE_USER']);

        $this->entityManager?->persist(new AuthAuditLogEntity()
            ->setUser($target)
            ->setLevel('info')
            ->setEvent('security.auth.login.success')
            ->setClientIp('127.0.0.1')
            ->setContext(['scope' => 'login']));
        $this->entityManager?->persist(new AuthAuditLogEntity()
            ->setUser($target)
            ->setLevel('warning')
            ->setEvent('security.auth.login.failed')
            ->setClientIp('198.51.100.8')
            ->setContext(['scope' => 'login']));
        $this->entityManager?->flush();

        $this->browser()->loginUser($admin);
        $this->browser()->request('GET', sprintf('/en/admin/users/%d/auth-events?level=warning&q=198.51.100.8', $target->getId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $content = (string) $this->browser()->getResponse()->getContent();
        self::assertStringContainsString('security.auth.login.failed', $content);
        self::assertStringNotContainsString('security.auth.login.success', $content);
    }

    public function testAdminCanExportUserAuthEventsCsvWithFilters(): void
    {
        $admin = $this->createUser('admin-auth-export@example.com', 'secret123', ['ROLE_ADMIN']);
        $target = $this->createUser('target-auth-export@example.com', 'secret123', ['ROLE_USER']);

        $this->entityManager?->persist(new AuthAuditLogEntity()
            ->setUser($target)
            ->setLevel('info')
            ->setEvent('security.auth.login.success')
            ->setClientIp('127.0.0.1')
            ->setContext(['scope' => 'login']));
        $this->entityManager?->persist(new AuthAuditLogEntity()
            ->setUser($target)
            ->setLevel('warning')
            ->setEvent('security.auth.login.failed')
            ->setClientIp('198.51.100.99')
            ->setContext(['scope' => 'login']));
        $this->entityManager?->flush();

        $this->browser()->loginUser($admin);
        $this->browser()->request('GET', sprintf('/en/admin/users/%d/auth-events/export.csv?level=warning&q=198.51.100', $target->getId()));

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $this->browser()->getResponse()->headers->get('content-type'));

        $content = $this->browser()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('occurred_at,event,level,client_ip,context_json', $content);
        self::assertStringContainsString('security.auth.login.failed', $content);
        self::assertStringNotContainsString('security.auth.login.success', $content);
    }

    public function testNonAdminCannotForceVerifyEmail(): void
    {
        $managed = $this->createUser('managed-force-non-admin@example.com', 'secret123', ['ROLE_USER']);
        $managed->setIsEmailVerified(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($managed);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/force-verify-email', $managed->getId()), [
            '_csrf_token' => 'invalid',
        ]);
        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanToggleExistingUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        self::assertTrue($managed->isActive());
        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
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

    public function testAdminActionRedirectPreservesGoogleFilter(): void
    {
        $admin = $this->createUser('admin-preserve@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($managed, 'google-sub-preserve');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?google=linked');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'google' => 'linked',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('google=linked', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminUsersPageRendersManagementActionsForManagedUser(): void
    {
        $admin = $this->createUser('admin-actions@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-actions@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($managed, 'google-sub-actions');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"]', $managed->getId())));
        self::assertCount(1, $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-admin"]', $managed->getId())));
        self::assertCount(1, $crawler->filter(sprintf('form[action*="/en/admin/users/%d/generate-reset-link"]', $managed->getId())));
        self::assertCount(1, $crawler->filter(sprintf('form[action*="/en/admin/users/%d/unlink-google"]', $managed->getId())));
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//thead//th[normalize-space()='Created at']"));
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr[td[1][normalize-space()='managed-actions@example.com']]/td[2]"));
        self::assertGreaterThanOrEqual(1, $crawler->filter('.admin-identity-badge-linked')->count());
        self::assertGreaterThanOrEqual(1, $crawler->filter('.admin-identity-meta')->count());
    }

    public function testAdminCanUnlinkGoogleIdentityForManagedUser(): void
    {
        $admin = $this->createUser('admin-unlink@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-unlink@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($managed, 'google-sub-unlink');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/unlink-google"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/unlink-google', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $identity = $this->entityManager?->getRepository(UserIdentityEntity::class)->findOneBy([
            'provider' => 'google',
            'providerUserId' => 'google-sub-unlink',
        ]);
        self::assertNull($identity);

        $audit = $this->findAuditForTargetAction('managed-unlink@example.com', 'user_unlink_google_identity');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertIsArray($audit->getContext());
        self::assertSame('google', $audit->getContext()['provider'] ?? null);
    }

    public function testUnlinkGoogleButtonIsDisabledWhenUserHasNoLocalPassword(): void
    {
        $admin = $this->createUser('admin-unlink-disabled@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-unlink-disabled@example.com', 'secret123', ['ROLE_USER']);
        $managed->setHasLocalPassword(false);
        $this->linkGoogleIdentity($managed, 'google-sub-unlink-disabled');
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr[td[1][normalize-space()='managed-unlink-disabled@example.com']]//form[contains(@action, '/unlink-google')]//button[@disabled]"));
        self::assertStringContainsString('Set a local password first before unlinking Google.', (string) $this->browser()->getResponse()->getContent());
    }

    public function testAdminCanFilterUsersByGoogleIdentityStatus(): void
    {
        $admin = $this->createUser('admin-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $linked = $this->createUser('linked-filter@example.com', 'secret123', ['ROLE_USER']);
        $this->createUser('unlinked-filter@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($linked, 'google-sub-filter');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?google=linked');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='linked-filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='unlinked-filter@example.com']"));
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?google=unlinked');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='unlinked-filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='linked-filter@example.com']"));
    }

    public function testAdminCanFilterUsersBySearchQuery(): void
    {
        $admin = $this->createUser('admin-search@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createUser('alpha.search@example.com', 'secret123', ['ROLE_USER']);
        $this->createUser('beta.search@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?q=alpha.search');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='alpha.search@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='beta.search@example.com']"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?q=@example.com');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='alpha.search@example.com']"));
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='beta.search@example.com']"));
    }

    public function testAdminCanFilterUsersByActiveStatus(): void
    {
        $admin = $this->createUser('admin-active-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $active = $this->createUser('active.filter@example.com', 'secret123', ['ROLE_USER']);
        $inactive = $this->createUser('inactive.filter@example.com', 'secret123', ['ROLE_USER']);
        $inactive->setIsActive(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?active=active');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='active.filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='inactive.filter@example.com']"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?active=inactive');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='inactive.filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='active.filter@example.com']"));
    }

    public function testAdminCanFilterUsersByRole(): void
    {
        $admin = $this->createUser('admin-role-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createUser('member-role-filter@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?role=admin');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='admin-role-filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='member-role-filter@example.com']"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?role=user');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='member-role-filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='admin-role-filter@example.com']"));
    }

    public function testAdminCanFilterUsersByVerificationStatus(): void
    {
        $admin = $this->createUser('admin-verified-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $verified = $this->createUser('verified.filter@example.com', 'secret123', ['ROLE_USER']);
        $unverified = $this->createUser('unverified.filter@example.com', 'secret123', ['ROLE_USER']);
        $verified->setIsEmailVerified(true);
        $unverified->setIsEmailVerified(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?verified=verified');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='verified.filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='unverified.filter@example.com']"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?verified=unverified');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='unverified.filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='verified.filter@example.com']"));
    }

    public function testAdminCanFilterUsersByLocalPasswordStatus(): void
    {
        $admin = $this->createUser('admin-local-password-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $enabled = $this->createUser('enabled.local-password@example.com', 'secret123', ['ROLE_USER']);
        $disabled = $this->createUser('disabled.local-password@example.com', 'secret123', ['ROLE_USER']);
        $enabled->setHasLocalPassword(true);
        $disabled->setHasLocalPassword(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?localPassword=enabled');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='enabled.local-password@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='disabled.local-password@example.com']"));

        $crawler = $this->browser()->request('GET', '/en/admin/users?localPassword=disabled');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='disabled.local-password@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='enabled.local-password@example.com']"));
    }

    public function testAdminCanFilterUsersByCreatedAtRange(): void
    {
        $admin = $this->createUser('admin-created-filter@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createUser('older-created-filter@example.com', 'secret123', ['ROLE_USER']);
        $this->createUser('newer-created-filter@example.com', 'secret123', ['ROLE_USER']);
        $this->setCreatedAtByEmail('older-created-filter@example.com', '2026-02-01 10:00:00');
        $this->setCreatedAtByEmail('newer-created-filter@example.com', '2026-02-25 10:00:00');
        $this->setCreatedAtByEmail('admin-created-filter@example.com', '2026-01-15 10:00:00');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?createdFrom=2026-02-20&createdTo=2026-02-28');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='newer-created-filter@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='older-created-filter@example.com']"));
    }

    public function testAdminUsersPageSupportsPaginationParameters(): void
    {
        $admin = $this->createUser('zzz-admin-page@example.com', 'secret123', ['ROLE_ADMIN']);
        for ($i = 1; $i <= 20; ++$i) {
            $this->createUser(sprintf('u%02d-page@example.com', $i), 'secret123', ['ROLE_USER']);
        }
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?perPage=20&page=2');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr"));
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='zzz-admin-page@example.com']"));
        self::assertCount(0, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr/td[1][normalize-space()='u01-page@example.com']"));
    }

    public function testAdminCanSortUsersByEmailDescending(): void
    {
        $admin = $this->createUser('admin-sort@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createUser('a-sort@example.com', 'secret123', ['ROLE_USER']);
        $this->createUser('z-sort@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?sort=email&dir=desc');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $firstEmail = trim($crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//tbody/tr[1]/td[1]")->text(''));
        self::assertSame('z-sort@example.com', $firstEmail);
        self::assertCount(1, $crawler->filterXPath("//table[contains(@class, 'admin-users-table')]//thead//th[@aria-sort='descending']/a[contains(@class, 'admin-sort-link')]/span[contains(@class, 'admin-sort-indicator') and normalize-space()='▼']"));
    }

    public function testAdminActionRedirectPreservesSearchQuery(): void
    {
        $admin = $this->createUser('admin-preserve-q@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-q@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?q=managed-preserve-q');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'q' => 'managed-preserve-q',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('q=managed-preserve-q', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminActionRedirectPreservesSortParameters(): void
    {
        $admin = $this->createUser('admin-preserve-sort@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-sort@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?sort=createdat&dir=desc');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'sort' => 'createdat',
            'dir' => 'desc',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('sort=createdat', $location);
        self::assertStringContainsString('dir=desc', $location);
    }

    public function testAdminActionRedirectPreservesActiveFilter(): void
    {
        $admin = $this->createUser('admin-preserve-active@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-active@example.com', 'secret123', ['ROLE_USER']);
        $managed->setIsActive(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?active=inactive');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'active' => 'inactive',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('active=inactive', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminActionRedirectPreservesPerPage(): void
    {
        $admin = $this->createUser('admin-preserve-per-page@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-per-page@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?perPage=50');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'perPage' => 50,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('perPage=50', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminActionRedirectPreservesPage(): void
    {
        $admin = $this->createUser('admin-preserve-page@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-page@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?perPage=20&page=2');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'perPage' => 20,
            'page' => 2,
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('page=2', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminActionRedirectPreservesVerificationAndLocalPasswordFilters(): void
    {
        $admin = $this->createUser('admin-preserve-security@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-security@example.com', 'secret123', ['ROLE_USER']);
        $managed->setIsEmailVerified(false)->setHasLocalPassword(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?verified=unverified&localPassword=disabled');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'verified' => 'unverified',
            'localPassword' => 'disabled',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('verified=unverified', $location);
        self::assertStringContainsString('localPassword=disabled', $location);
    }

    public function testAdminActionRedirectPreservesRoleFilter(): void
    {
        $admin = $this->createUser('admin-preserve-role@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-role@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?role=user');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'role' => 'user',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('role=user', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testAdminActionRedirectPreservesCreatedRangeFilters(): void
    {
        $admin = $this->createUser('admin-preserve-created-range@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-preserve-created-range@example.com', 'secret123', ['ROLE_USER']);
        $this->setCreatedAtByEmail('admin-preserve-created-range@example.com', '2026-02-05 10:00:00');
        $this->setCreatedAtByEmail('managed-preserve-created-range@example.com', '2026-02-10 10:00:00');
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users?createdFrom=2026-02-01&createdTo=2026-02-28');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-active"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-active', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'createdFrom' => '2026-02-01',
            'createdTo' => '2026-02-28',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('createdFrom=2026-02-01', $location);
        self::assertStringContainsString('createdTo=2026-02-28', $location);
    }

    public function testAdminCanResendVerificationEmailForUnverifiedUser(): void
    {
        $admin = $this->createUser('admin-resend@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-resend@example.com', 'secret123', ['ROLE_USER']);
        $managed->setIsEmailVerified(false);
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/resend-verification"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/resend-verification', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed-resend@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNotNull($updated->getEmailVerificationTokenHash());
        self::assertNotNull($updated->getEmailVerificationRequestedAt());
        self::assertNotNull($updated->getEmailVerificationExpiresAt());

        $audit = $this->findAuditForTargetAction('managed-resend@example.com', 'user_resend_verification_email');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
    }

    public function testAdminCanForceVerifyEmailForUnverifiedUser(): void
    {
        $admin = $this->createUser('admin-force@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed-force@example.com', 'secret123', ['ROLE_USER']);
        $managed
            ->setIsEmailVerified(false)
            ->setEmailVerificationTokenHash(hash('sha256', 'token'))
            ->setEmailVerificationRequestedAt(new DateTimeImmutable('-1 minute'))
            ->setEmailVerificationExpiresAt(new DateTimeImmutable('+1 hour'));
        $this->entityManager?->flush();
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/force-verify-email"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/force-verify-email', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed-force@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertTrue($updated->isEmailVerified());
        self::assertNull($updated->getEmailVerificationTokenHash());
        self::assertNull($updated->getEmailVerificationRequestedAt());
        self::assertNull($updated->getEmailVerificationExpiresAt());

        $audit = $this->findAuditForTargetAction('managed-force@example.com', 'user_force_verify_email');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
    }

    public function testAdminCanExportUsersCsvWithFilters(): void
    {
        $admin = $this->createUser('admin-export@example.com', 'secret123', ['ROLE_ADMIN']);
        $linkedUser = $this->createUser('linked-export@example.com', 'secret123', ['ROLE_USER']);
        $this->linkGoogleIdentity($linkedUser, 'google-sub-export');
        $this->createUser('plain-export@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $this->browser()->request('GET', '/en/admin/users/export?google=linked&role=user&sort=email&dir=asc');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('text/csv', (string) $this->browser()->getResponse()->headers->get('content-type'));

        $content = (string) $this->browser()->getResponse()->getContent();
        self::assertStringContainsString('email,created_at,is_active,is_email_verified,has_local_password,roles,google_linked,google_linked_since', $content);
        self::assertStringContainsString('linked-export@example.com', $content);
        self::assertStringNotContainsString('plain-export@example.com', $content);
    }

    public function testAdminUsersCsvExportUsesUtf8BomAndSanitizesFormulaLikeCells(): void
    {
        $admin = $this->createUser('admin-export-bom@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createUser('=danger@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $this->browser()->request('GET', '/en/admin/users/export?q=%40example.com');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $content = (string) $this->browser()->getResponse()->getContent();

        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString("'=danger@example.com", $content);
    }

    public function testAdminCanToggleManagedUserRole(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        self::assertNotContains('ROLE_ADMIN', $managed->getRoles());
        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/toggle-admin"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/toggle-admin', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertContains('ROLE_ADMIN', $updated->getRoles());

        $audit = $this->findAuditForTargetAction('managed@example.com', 'user_toggle_admin');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertIsArray($audit->getContext());
        self::assertSame(true, $audit->getContext()['isAdmin'] ?? null);
    }

    public function testAdminCanGenerateResetLinkForExistingUser(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/generate-reset-link"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/generate-reset-link', $managed->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $updated);
        self::assertNotNull($updated->getResetPasswordTokenHash());
        self::assertNotNull($updated->getResetPasswordExpiresAt());
        self::assertNotNull($updated->getResetPasswordRequestedAt());

        $audit = $this->findAuditForTargetAction('managed@example.com', 'user_generate_reset_link');
        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        self::assertIsArray($audit->getContext());
        self::assertIsString($audit->getContext()['expiresAt'] ?? null);
    }

    public function testAdminCannotGenerateResetLinkTooFrequently(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $managed = $this->createUser('managed@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/en/admin/users');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/generate-reset-link"] input[name="_csrf_token"]', $managed->getId()));
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/generate-reset-link', $managed->getId()), [
            '_csrf_token' => $csrfToken,
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $first = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $first);
        self::assertNotNull($first->getResetPasswordTokenHash());
        $firstHash = $first->getResetPasswordTokenHash();

        $this->browser()->request('POST', sprintf('/en/admin/users/%d/generate-reset-link', $managed->getId()), [
            '_csrf_token' => $csrfToken,
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $second = $this->findUserByEmail('managed@example.com');
        self::assertInstanceOf(UserEntity::class, $second);
        self::assertSame($firstHash, $second->getResetPasswordTokenHash());

        $rateLimitedAudit = $this->findAuditForTargetAction('managed@example.com', 'user_generate_reset_link_rate_limited');
        self::assertInstanceOf(AdminAuditLogEntity::class, $rateLimitedAudit);
        self::assertIsArray($rateLimitedAudit->getContext());
        self::assertIsInt($rateLimitedAudit->getContext()['remainingSeconds'] ?? null);
    }

    public function testAdminCannotGenerateTooManyResetLinksGloballyInShortWindow(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $targets = [];
        for ($i = 1; $i <= 11; ++$i) {
            $targets[] = $this->createUser(sprintf('managed-%d@example.com', $i), 'secret123', ['ROLE_USER']);
        }

        $crawler = $this->browser()->request('GET', '/en/admin/users');

        foreach ($targets as $index => $target) {
            $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/users/%d/generate-reset-link"] input[name="_csrf_token"]', $target->getId()));
            self::assertCount(1, $tokenNode);

            $this->browser()->request('POST', sprintf('/en/admin/users/%d/generate-reset-link', $target->getId()), [
                '_csrf_token' => (string) $tokenNode->attr('value'),
            ]);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

            if ($index < 10) {
                continue;
            }

            $this->entityManager?->clear();
            $blocked = $this->findUserByEmail('managed-11@example.com');
            self::assertInstanceOf(UserEntity::class, $blocked);
            self::assertNull($blocked->getResetPasswordTokenHash());

            $audit = $this->findAuditForTargetAction('managed-11@example.com', 'user_generate_reset_link_global_rate_limited');
            self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
            self::assertIsArray($audit->getContext());
            self::assertSame(10, $audit->getContext()['maxRequests'] ?? null);
        }
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

    private function findUserByEmail(string $email): ?UserEntity
    {
        $result = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        return $result instanceof UserEntity ? $result : null;
    }

    private function linkGoogleIdentity(UserEntity $user, string $providerUserId): void
    {
        $identity = new UserIdentityEntity()
            ->setUser($user)
            ->setProvider('google')
            ->setProviderUserId($providerUserId)
            ->setProviderEmail($user->getEmail());

        $this->entityManager?->persist($identity);
        $this->entityManager?->flush();
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
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE auth_audit_log, admin_audit_log, user_identity, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function setCreatedAtByEmail(string $email, string $createdAt): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE app_user SET created_at = :createdAt, updated_at = :createdAt WHERE email = :email',
            [
                'createdAt' => $createdAt,
                'email' => $email,
            ],
        );
        $this->entityManager->clear();
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
