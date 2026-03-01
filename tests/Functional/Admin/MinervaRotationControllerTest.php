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

use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class MinervaRotationControllerTest extends WebTestCase
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

    public function testNonAdminCannotAccessAdminPage(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/admin/minerva-rotation');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessAdminPage(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);
        $this->browser()->request('GET', '/admin/minerva-rotation');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
    }

    public function testTimelineTableColumnsAreOrderedLikeFrontWithSourceAndStatusLast(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $headers = $crawler->filter('table.admin-minerva-timeline-table thead th');
        self::assertCount(6, $headers);

        $headerTexts = $headers->each(static fn ($node): string => trim(preg_replace('/\s+/', ' ', $node->text()) ?? ''));
        $translator = $this->browser()->getContainer()->get(TranslatorInterface::class);
        \assert($translator instanceof TranslatorInterface);
        $locale = $this->browser()->getRequest()->getLocale();

        self::assertSame([
            $translator->trans('minerva.list_cycle', locale: $locale),
            $translator->trans('minerva.location', locale: $locale),
            $translator->trans('minerva.starts_at', locale: $locale),
            $translator->trans('minerva.ends_at', locale: $locale),
            $translator->trans('admin_minerva.source', locale: $locale),
            $translator->trans('minerva.status', locale: $locale),
        ], $headerTexts);
    }

    public function testAdminFormsExposeMinervaDatepickerController(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        self::assertCount(1, $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"][data-controller~="minerva-admin-datepicker"]'));
        self::assertCount(1, $crawler->filter('form[action*="/admin/minerva-rotation/refresh"][data-controller~="minerva-admin-datepicker"]'));
        self::assertCount(1, $crawler->filter('form[action*="/admin/minerva-rotation/override/create"][data-controller~="minerva-admin-datepicker"]'));
    }

    public function testAdminPageUsesQueryDateRangeInForms(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation?from=2026-05-01&to=2026-06-30');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $regenerateFrom = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="from"]');
        $regenerateTo = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="to"]');
        self::assertCount(1, $regenerateFrom);
        self::assertCount(1, $regenerateTo);
        self::assertSame('2026-05-01', $regenerateFrom->attr('value'));
        self::assertSame('2026-06-30', $regenerateTo->attr('value'));

        $refreshFrom = $crawler->filter('form[action*="/admin/minerva-rotation/refresh"] input[name="from"]');
        $refreshTo = $crawler->filter('form[action*="/admin/minerva-rotation/refresh"] input[name="to"]');
        self::assertCount(1, $refreshFrom);
        self::assertCount(1, $refreshTo);
        self::assertSame('2026-05-01', $refreshFrom->attr('value'));
        self::assertSame('2026-06-30', $refreshTo->attr('value'));

        $overrideHiddenFrom = $crawler->filter('form[action*="/admin/minerva-rotation/override/create"] input[name="from"]');
        $overrideHiddenTo = $crawler->filter('form[action*="/admin/minerva-rotation/override/create"] input[name="to"]');
        self::assertCount(1, $overrideHiddenFrom);
        self::assertCount(1, $overrideHiddenTo);
        self::assertSame('2026-05-01', $overrideHiddenFrom->attr('value'));
        self::assertSame('2026-06-30', $overrideHiddenTo->attr('value'));
    }

    public function testAdminPageFallsBackToDefaultRangeWhenQueryDatesAreInvalid(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation?from=invalid-date&to=2026-99-99');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $regenerateFrom = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="from"]');
        $regenerateTo = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="to"]');
        self::assertCount(1, $regenerateFrom);
        self::assertCount(1, $regenerateTo);

        $fromValue = (string) $regenerateFrom->attr('value');
        $toValue = (string) $regenerateTo->attr('value');

        self::assertNotSame('invalid-date', $fromValue);
        self::assertNotSame('2026-99-99', $toValue);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $fromValue);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $toValue);
    }

    public function testAdminCanRegenerateRotationFromForm(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/regenerate', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-03-01', $location);
        self::assertStringContainsString('to=2026-03-20', $location);

        $count = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertTrue(is_int($count) || is_numeric($count));
        self::assertGreaterThan(0, (int) $count);
    }

    public function testAdminRegenerateRejectsInvalidRangeAndPreservesQueryContext(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation?from=2026-04-01&to=2026-04-20');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/regenerate', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-04-20',
            'to' => '2026-04-01',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-04-20', $location);
        self::assertStringContainsString('to=2026-04-01', $location);
    }

    public function testAdminCanRunCoverageRefreshFromForm(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/refresh"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/refresh', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
            'dryRun' => '1',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringStartsWith('/admin/minerva-rotation', $location);
        self::assertStringContainsString('locale=', $location);
        self::assertStringContainsString('from=2026-03-01', $location);
        self::assertStringContainsString('to=2026-03-20', $location);

        $audit = $this->entityManager?->getRepository(AdminAuditLogEntity::class)
            ->createQueryBuilder('a')
            ->where('a.action = :action')
            ->setParameter('action', 'minerva_refresh_dry_run')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(AdminAuditLogEntity::class, $audit);
        $context = $audit->getContext();
        self::assertIsArray($context);
        self::assertSame(true, $context['dryRun'] ?? null);
    }

    public function testAdminPageDisplaysLatestRefreshSummaryFromAuditLog(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/refresh"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/refresh', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-09-01',
            'to' => '2026-09-20',
            'dryRun' => '1',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $lastRefreshNode = $crawler->filter('[data-minerva-last-refresh="1"]');
        self::assertCount(1, $lastRefreshNode);
        self::assertSame('minerva_refresh_dry_run', $lastRefreshNode->attr('data-minerva-last-refresh-action'));
    }

    public function testAdminRefreshRejectsInvalidRangeAndPreservesQueryContext(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation?from=2026-04-01&to=2026-04-20');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/refresh"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/refresh', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-04-20',
            'to' => '2026-04-01',
            'dryRun' => '1',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-04-20', $location);
        self::assertStringContainsString('to=2026-04-01', $location);
    }

    public function testAdminRefreshRejectsInvalidCsrfAndPreservesQueryContext(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $this->browser()->request('POST', '/admin/minerva-rotation/refresh', [
            '_csrf_token' => 'invalid-token',
            'from' => '2026-08-01',
            'to' => '2026-08-31',
            'dryRun' => '1',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-08-01', $location);
        self::assertStringContainsString('to=2026-08-31', $location);
    }

    public function testAdminPageDisplaysCoverageFreshnessSummary(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);
        $this->persistRotation(
            source: MinervaRotationSourceEnum::GENERATED,
            location: 'Foundation',
            listCycle: 3,
            startsAt: '2026-03-02T12:00:00+00:00',
            endsAt: '2026-03-04T12:00:00+00:00',
        );
        $this->persistRotation(
            source: MinervaRotationSourceEnum::MANUAL,
            location: 'Crater',
            listCycle: 4,
            startsAt: '2026-03-05T12:00:00+00:00',
            endsAt: '2026-03-08T12:00:00+00:00',
        );

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());

        $freshnessNode = $crawler->filter('[data-minerva-freshness="1"]');
        self::assertCount(1, $freshnessNode);

        $expected = (int) $freshnessNode->attr('data-minerva-expected-windows');
        $missing = (int) $freshnessNode->attr('data-minerva-missing-windows');
        $covered = (string) $freshnessNode->attr('data-minerva-covered');
        $stale = (string) $freshnessNode->attr('data-minerva-stale');

        self::assertGreaterThan(0, $expected);
        self::assertGreaterThanOrEqual(0, $missing);
        self::assertLessThanOrEqual($expected, $missing);
        self::assertContains($covered, ['0', '1']);
        self::assertContains($stale, ['0', '1']);
        self::assertCount(1, $crawler->filter('a[href*="/admin/audit-logs"][href*="q=minerva_"]'));
    }

    public function testAdminCanCreateAndDeleteManualOverride(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/override/create"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/override/create', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
            'location' => 'Foundation',
            'listCycle' => '9',
            'startsAt' => '2026-04-01T12:00',
            'endsAt' => '2026-04-03T12:00',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $createLocation = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-03-01', $createLocation);
        self::assertStringContainsString('to=2026-03-20', $createLocation);

        $manual = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findOneBy(['source' => MinervaRotationSourceEnum::MANUAL->value]);
        self::assertInstanceOf(MinervaRotationEntity::class, $manual);
        $manualId = $manual->getId();
        self::assertIsInt($manualId);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $deleteTokenNode = $crawler->filter(sprintf(
            'form[action*="/admin/minerva-rotation/override/%d/delete"] input[name="_csrf_token"]',
            $manualId,
        ));
        self::assertCount(1, $deleteTokenNode);

        $this->browser()->request('POST', sprintf('/admin/minerva-rotation/override/%d/delete', $manualId), [
            '_csrf_token' => (string) $deleteTokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $deleteLocation = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-03-01', $deleteLocation);
        self::assertStringContainsString('to=2026-03-20', $deleteLocation);

        $deleted = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findOneBy(['id' => $manualId]);
        self::assertNull($deleted);
    }

    public function testAdminOverrideCreateRejectsInvalidPayloadAndPreservesQueryContext(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation?from=2026-07-01&to=2026-07-31');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/override/create"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/override/create', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'location' => '',
            'listCycle' => '0',
            'startsAt' => '2026-07-10T12:00',
            'endsAt' => '2026-07-09T12:00',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringContainsString('from=2026-07-01', $location);
        self::assertStringContainsString('to=2026-07-31', $location);
    }

    public function testRegenerationKeepsManualOverrideAndSkipsOverlappingGeneratedWindow(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $this->persistRotation(
            source: MinervaRotationSourceEnum::MANUAL,
            location: 'Foundation',
            listCycle: 8,
            startsAt: '2026-03-12T12:00:00+00:00',
            endsAt: '2026-03-16T12:00:00+00:00',
        );

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/regenerate', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $rows = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findBy([], ['startsAt' => 'ASC']);
        self::assertIsArray($rows);
        /** @var list<MinervaRotationEntity> $rows */
        $manualCount = 0;
        $generatedCount = 0;
        foreach ($rows as $row) {
            if (MinervaRotationSourceEnum::MANUAL === $row->getSource()) {
                ++$manualCount;
                continue;
            }
            if (
                $row->getStartsAt() < new DateTimeImmutable('2026-03-16T12:00:00+00:00')
                && $row->getEndsAt() > new DateTimeImmutable('2026-03-12T12:00:00+00:00')
            ) {
                self::fail('Generated row should be skipped when overlapped by manual override.');
            }
            ++$generatedCount;
        }

        self::assertSame(1, $manualCount);
        self::assertGreaterThan(0, $generatedCount);
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
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation, contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function persistRotation(
        MinervaRotationSourceEnum $source,
        string $location,
        int $listCycle,
        string $startsAt,
        string $endsAt,
    ): void {
        $rotation = new MinervaRotationEntity()
            ->setSource($source)
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt));

        $this->entityManager?->persist($rotation);
        $this->entityManager?->flush();
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
