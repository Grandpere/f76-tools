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

use App\Support\Application\Audit\AuditLogListApplicationService;
use App\Support\Application\Audit\AuditLogListQuery;
use App\Support\Application\Audit\AuditLogReadRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuditLogListApplicationServiceTest extends TestCase
{
    public function testListSanitizesInputsAndReturnsResult(): void
    {
        $repository = new InMemoryAuditLogReadRepository([
            ['rows' => [], 'total' => 12],
        ], ['a1', 'a2']);

        $service = new AuditLogListApplicationService($repository);
        $result = $service->list(AuditLogListQuery::fromRaw('  login ', ' user_toggle_active ', 1, 40));

        self::assertSame('login', $result->query);
        self::assertSame('user_toggle_active', $result->action);
        self::assertSame(1, $result->page);
        self::assertSame(40, $result->perPage);
        self::assertSame(1, $result->totalPages);
        self::assertSame(['a1', 'a2'], $result->actions);
        self::assertSame(['login', 'user_toggle_active', 1, 40], $repository->calls[0]);
    }

    public function testListClampsPageAndRefetchesWhenNeeded(): void
    {
        $repository = new InMemoryAuditLogReadRepository([
            ['rows' => [], 'total' => 31],
            ['rows' => [], 'total' => 31],
        ], []);

        $service = new AuditLogListApplicationService($repository);
        $result = $service->list(AuditLogListQuery::fromRaw('', '', 99, 10));

        self::assertSame(4, $result->page);
        self::assertSame(4, $result->totalPages);
        self::assertCount(2, $repository->calls);
        self::assertSame(['', '', 99, 10], $repository->calls[0]);
        self::assertSame(['', '', 4, 10], $repository->calls[1]);
    }

    public function testListUsesDefaultsForInvalidValues(): void
    {
        $repository = new InMemoryAuditLogReadRepository([
            ['rows' => [], 'total' => 0],
        ], []);

        $service = new AuditLogListApplicationService($repository);
        $result = $service->list(AuditLogListQuery::fromRaw(null, null, null, null));

        self::assertSame('', $result->query);
        self::assertSame('', $result->action);
        self::assertSame(1, $result->page);
        self::assertSame(30, $result->perPage);
        self::assertSame(['', '', 1, 30], $repository->calls[0]);
    }
}

/**
 * @internal
 */
final class InMemoryAuditLogReadRepository implements AuditLogReadRepositoryInterface
{
    /**
     * @var list<array{rows: list<\App\Support\Domain\Entity\AdminAuditLogEntity>, total: int}>
     */
    private array $results;

    /**
     * @var list<string>
     */
    private array $actions;

    /**
     * @var list<array{0: string, 1: string, 2: int, 3: int}>
     */
    public array $calls = [];

    /**
     * @param list<array{rows: list<\App\Support\Domain\Entity\AdminAuditLogEntity>, total: int}> $results
     * @param list<string>                                                                        $actions
     */
    public function __construct(array $results, array $actions)
    {
        $this->results = $results;
        $this->actions = $actions;
    }

    public function findPaginated(string $query, string $action, int $page, int $perPage): array
    {
        $this->calls[] = [$query, $action, $page, $perPage];

        $result = array_shift($this->results);
        if (!is_array($result)) {
            return ['rows' => [], 'total' => 0];
        }

        return $result;
    }

    public function findDistinctActions(): array
    {
        return $this->actions;
    }

    public function findForExport(string $query, string $action, int $maxRows): array
    {
        return [];
    }
}
