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

namespace App\Tests\Unit\Catalog\NukeCode;

use App\Catalog\Application\NukeCode\NukeCodeReadApplicationService;
use App\Catalog\Application\NukeCode\NukeCodeReadRepository;
use App\Catalog\Application\NukeCode\NukeCodeResetCalculator;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class NukeCodeReadApplicationServiceTest extends TestCase
{
    public function testReadsFromRemoteOnlyOnceWhenCacheIsWarm(): void
    {
        $repository = new InMemoryNukeCodeReadRepository([
            ['alpha' => '11111111', 'bravo' => '22222222', 'charlie' => '33333333'],
        ]);

        $service = new NukeCodeReadApplicationService(
            $repository,
            new NukeCodeResetCalculator(),
            new ArrayAdapter(),
            0,
            1800,
        );

        $first = $service->getCurrent();
        $second = $service->getCurrent();

        self::assertSame('11111111', $first->alpha);
        self::assertSame('11111111', $second->alpha);
        self::assertSame(1, $repository->calls);
    }

    public function testReturnsStaleSnapshotWhenRemoteFailsAndStaleCacheExists(): void
    {
        $cache = new ArrayAdapter();
        $calculator = new NukeCodeResetCalculator();

        $warmRepository = new InMemoryNukeCodeReadRepository([
            ['alpha' => '11111111', 'bravo' => '22222222', 'charlie' => '33333333'],
        ]);
        $warmService = new NukeCodeReadApplicationService($warmRepository, $calculator, $cache, 0, 1800);
        $warmService->getCurrent();

        $cache->deleteItem('nuke_codes.current.v1');

        $failingRepository = new InMemoryNukeCodeReadRepository([]);
        $failingRepository->throwOnFetch = true;
        $service = new NukeCodeReadApplicationService($failingRepository, $calculator, $cache, 0, 1800);

        $snapshot = $service->getCurrent();

        self::assertTrue($snapshot->stale);
        self::assertSame('11111111', $snapshot->alpha);
    }
}

final class InMemoryNukeCodeReadRepository implements NukeCodeReadRepository
{
    /** @var list<array{alpha: string, bravo: string, charlie: string}> */
    private array $rows;

    public int $calls = 0;
    public bool $throwOnFetch = false;

    /**
     * @param list<array{alpha: string, bravo: string, charlie: string}> $rows
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function fetchCurrent(): array
    {
        ++$this->calls;

        if ($this->throwOnFetch) {
            throw new RuntimeException('upstream failure');
        }

        $row = array_shift($this->rows);
        if (!is_array($row)) {
            throw new RuntimeException('no more fake rows');
        }

        return $row;
    }
}
