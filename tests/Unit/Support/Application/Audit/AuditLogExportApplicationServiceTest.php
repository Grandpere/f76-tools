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

use App\Support\Application\Audit\AuditLogExportApplicationService;
use App\Support\Application\Audit\AuditLogExportQuery;
use App\Support\Application\Audit\AuditLogReadRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class AuditLogExportApplicationServiceTest extends TestCase
{
    public function testExportSanitizesInputsAndDelegatesToRepository(): void
    {
        $repository = new InMemoryAuditLogExportRepository();
        $service = new AuditLogExportApplicationService($repository);

        $result = $service->export(AuditLogExportQuery::fromRaw('  login ', ' user_toggle_active '));

        self::assertSame('login', $result->query);
        self::assertSame('user_toggle_active', $result->action);
        self::assertSame(['login', 'user_toggle_active', 10000], $repository->lastCall);
    }
}

/**
 * @internal
 */
final class InMemoryAuditLogExportRepository implements AuditLogReadRepositoryInterface
{
    /**
     * @var array{0: string, 1: string, 2: int}|null
     */
    public ?array $lastCall = null;

    public function findPaginated(string $query, string $action, int $page, int $perPage): array
    {
        return ['rows' => [], 'total' => 0];
    }

    public function findDistinctActions(): array
    {
        return [];
    }

    public function findForExport(string $query, string $action, int $maxRows): array
    {
        $this->lastCall = [$query, $action, $maxRows];

        return [];
    }
}
