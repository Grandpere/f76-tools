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

namespace App\Tests\Unit\Support\Application\Audit;

use App\Identity\Domain\Entity\UserEntity;
use App\Support\Application\Audit\AuditLogReadRepository;
use App\Support\Application\Audit\LatestMinervaRefreshSummaryApplicationService;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class LatestMinervaRefreshSummaryApplicationServiceTest extends TestCase
{
    public function testResolveReturnsNullWhenNoMatchingAuditEntry(): void
    {
        $service = new LatestMinervaRefreshSummaryApplicationService(new InMemoryLatestMinervaAuditLogReadRepository(null), new ArrayAdapter());

        self::assertNull($service->resolve());
    }

    public function testResolveMapsSummaryFromLatestAuditEntry(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash');

        $entry = new AdminAuditLogEntity()
            ->setActorUser($actor)
            ->setAction('minerva_refresh_performed')
            ->setOccurredAt(new DateTimeImmutable('2026-03-01T10:00:00+00:00'))
            ->setContext([
                'expectedWindows' => '120',
                'missingWindows' => 2,
                'deleted' => 1,
                'inserted' => 4,
                'skipped' => 0,
                'dryRun' => false,
            ]);

        $repository = new InMemoryLatestMinervaAuditLogReadRepository($entry);
        $service = new LatestMinervaRefreshSummaryApplicationService($repository, new ArrayAdapter());
        $summary = $service->resolve();

        self::assertIsArray($summary);
        self::assertSame('minerva_refresh_performed', $summary['action']);
        self::assertSame('admin@example.com', $summary['actorEmail']);
        self::assertSame(120, $summary['expectedWindows']);
        self::assertSame(2, $summary['missingWindows']);
        self::assertSame(1, $summary['deleted']);
        self::assertSame(4, $summary['inserted']);
        self::assertSame(0, $summary['skipped']);
        self::assertFalse($summary['dryRun']);
        self::assertSame(1, $repository->latestCalls);
    }

    public function testResolveUsesCacheForConsecutiveCalls(): void
    {
        $actor = new UserEntity()
            ->setEmail('admin@example.com')
            ->setPassword('hash');

        $entry = new AdminAuditLogEntity()
            ->setActorUser($actor)
            ->setAction('minerva_refresh_dry_run')
            ->setOccurredAt(new DateTimeImmutable('2026-03-01T10:00:00+00:00'))
            ->setContext([
                'expectedWindows' => 60,
                'missingWindows' => 0,
                'deleted' => 0,
                'inserted' => 0,
                'skipped' => 0,
                'dryRun' => true,
            ]);

        $repository = new InMemoryLatestMinervaAuditLogReadRepository($entry);
        $service = new LatestMinervaRefreshSummaryApplicationService($repository, new ArrayAdapter());

        $first = $service->resolve();
        $second = $service->resolve();

        self::assertSame($first, $second);
        self::assertSame(1, $repository->latestCalls);
    }
}

/**
 * @internal
 */
final class InMemoryLatestMinervaAuditLogReadRepository implements AuditLogReadRepository
{
    public int $latestCalls = 0;

    public function __construct(
        private readonly ?AdminAuditLogEntity $latest,
    ) {
    }

    public function findPaginated(string $query, string $action, int $page, int $perPage): array
    {
        return ['rows' => [], 'total' => 0];
    }

    public function findRowsPage(string $query, string $action, int $page, int $perPage): array
    {
        return [];
    }

    public function findDistinctActions(): array
    {
        return [];
    }

    public function findForExport(string $query, string $action, int $maxRows): array
    {
        return [];
    }

    public function findLatestByActions(array $actions): ?AdminAuditLogEntity
    {
        ++$this->latestCalls;

        return $this->latest;
    }
}
