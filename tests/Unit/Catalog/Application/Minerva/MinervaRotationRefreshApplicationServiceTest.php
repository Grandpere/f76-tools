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

namespace App\Tests\Unit\Catalog\Application\Minerva;

use App\Catalog\Application\Minerva\MinervaRotationGenerator;
use App\Catalog\Application\Minerva\MinervaRotationRefreshApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationRepository;
use App\Catalog\Application\Minerva\MinervaRotationRegenerator;
use App\Catalog\Domain\Entity\MinervaRotationEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class MinervaRotationRefreshApplicationServiceTest extends TestCase
{
    public function testRefreshDoesNotRegenerateWhenCoverageIsComplete(): void
    {
        $from = new DateTimeImmutable('2026-03-01 00:00:00');
        $to = new DateTimeImmutable('2026-03-31 23:59:59');

        $generatedRows = [
            [
                'location' => 'Foundation',
                'listCycle' => 1,
                'startsAt' => new DateTimeImmutable('2026-03-02 12:00:00'),
                'endsAt' => new DateTimeImmutable('2026-03-04 12:00:00'),
            ],
        ];

        /** @var MinervaRotationGenerator&MockObject $generationService */
        $generationService = $this->createMock(MinervaRotationGenerator::class);
        $generationService
            ->expects(self::once())
            ->method('generate')
            ->with($from, $to)
            ->willReturn($generatedRows);

        /** @var MinervaRotationRegenerationRepository&MockObject $repository */
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverlappingRange')
            ->with($from, $to)
            ->willReturn([
                new MinervaRotationEntity()
                    ->setLocation('Foundation')
                    ->setListCycle(1)
                    ->setStartsAt(new DateTimeImmutable('2026-03-02 12:00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-04 12:00:00')),
            ]);

        /** @var MinervaRotationRegenerator&MockObject $regenerationService */
        $regenerationService = $this->createMock(MinervaRotationRegenerator::class);
        $regenerationService->expects(self::never())->method('regenerate');

        $service = new MinervaRotationRefreshApplicationService($generationService, $regenerationService, $repository, new ArrayAdapter());
        $result = $service->refresh($from, $to);

        self::assertSame(1, $result['expectedWindows']);
        self::assertSame(0, $result['missingWindows']);
        self::assertTrue($result['covered']);
        self::assertFalse($result['performed']);
        self::assertSame(0, $result['deleted']);
        self::assertSame(0, $result['inserted']);
        self::assertSame(0, $result['skipped']);
    }

    public function testRefreshRegeneratesWhenCoverageHasGaps(): void
    {
        $from = new DateTimeImmutable('2026-03-01 00:00:00');
        $to = new DateTimeImmutable('2026-03-31 23:59:59');

        $generatedRows = [
            [
                'location' => 'Foundation',
                'listCycle' => 1,
                'startsAt' => new DateTimeImmutable('2026-03-02 12:00:00'),
                'endsAt' => new DateTimeImmutable('2026-03-04 12:00:00'),
            ],
            [
                'location' => 'Crater',
                'listCycle' => 2,
                'startsAt' => new DateTimeImmutable('2026-03-09 12:00:00'),
                'endsAt' => new DateTimeImmutable('2026-03-11 12:00:00'),
            ],
        ];

        /** @var MinervaRotationGenerator&MockObject $generationService */
        $generationService = $this->createMock(MinervaRotationGenerator::class);
        $generationService
            ->expects(self::once())
            ->method('generate')
            ->with($from, $to)
            ->willReturn($generatedRows);

        /** @var MinervaRotationRegenerationRepository&MockObject $repository */
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverlappingRange')
            ->with($from, $to)
            ->willReturn([
                new MinervaRotationEntity()
                    ->setLocation('Foundation')
                    ->setListCycle(1)
                    ->setStartsAt(new DateTimeImmutable('2026-03-02 12:00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-04 12:00:00')),
            ]);

        /** @var MinervaRotationRegenerator&MockObject $regenerationService */
        $regenerationService = $this->createMock(MinervaRotationRegenerator::class);
        $regenerationService
            ->expects(self::once())
            ->method('regenerate')
            ->with($from, $to)
            ->willReturn([
                'deleted' => 1,
                'inserted' => 2,
                'skipped' => 0,
            ]);

        $service = new MinervaRotationRefreshApplicationService($generationService, $regenerationService, $repository, new ArrayAdapter());
        $result = $service->refresh($from, $to);

        self::assertSame(2, $result['expectedWindows']);
        self::assertSame(1, $result['missingWindows']);
        self::assertFalse($result['covered']);
        self::assertTrue($result['performed']);
        self::assertSame(1, $result['deleted']);
        self::assertSame(2, $result['inserted']);
        self::assertSame(0, $result['skipped']);
    }

    public function testRefreshDryRunDoesNotRegenerateWhenCoverageHasGaps(): void
    {
        $from = new DateTimeImmutable('2026-03-01 00:00:00');
        $to = new DateTimeImmutable('2026-03-31 23:59:59');

        /** @var MinervaRotationGenerator&MockObject $generationService */
        $generationService = $this->createMock(MinervaRotationGenerator::class);
        $generationService
            ->expects(self::once())
            ->method('generate')
            ->with($from, $to)
            ->willReturn([
                [
                    'location' => 'Foundation',
                    'listCycle' => 1,
                    'startsAt' => new DateTimeImmutable('2026-03-02 12:00:00'),
                    'endsAt' => new DateTimeImmutable('2026-03-04 12:00:00'),
                ],
            ]);

        /** @var MinervaRotationRegenerationRepository&MockObject $repository */
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverlappingRange')
            ->with($from, $to)
            ->willReturn([]);

        /** @var MinervaRotationRegenerator&MockObject $regenerationService */
        $regenerationService = $this->createMock(MinervaRotationRegenerator::class);
        $regenerationService->expects(self::never())->method('regenerate');

        $service = new MinervaRotationRefreshApplicationService($generationService, $regenerationService, $repository, new ArrayAdapter());
        $result = $service->refresh($from, $to, true);

        self::assertSame(1, $result['expectedWindows']);
        self::assertSame(1, $result['missingWindows']);
        self::assertFalse($result['covered']);
        self::assertFalse($result['performed']);
    }

    public function testDryRunUsesCacheForSameRange(): void
    {
        $from = new DateTimeImmutable('2026-03-01 00:00:00');
        $to = new DateTimeImmutable('2026-03-31 23:59:59');

        /** @var MinervaRotationGenerator&MockObject $generationService */
        $generationService = $this->createMock(MinervaRotationGenerator::class);
        $generationService
            ->expects(self::once())
            ->method('generate')
            ->with($from, $to)
            ->willReturn([
                [
                    'location' => 'Foundation',
                    'listCycle' => 1,
                    'startsAt' => new DateTimeImmutable('2026-03-02 12:00:00'),
                    'endsAt' => new DateTimeImmutable('2026-03-04 12:00:00'),
                ],
            ]);

        /** @var MinervaRotationRegenerationRepository&MockObject $repository */
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOverlappingRange')
            ->with($from, $to)
            ->willReturn([]);

        /** @var MinervaRotationRegenerator&MockObject $regenerationService */
        $regenerationService = $this->createMock(MinervaRotationRegenerator::class);
        $regenerationService->expects(self::never())->method('regenerate');

        $service = new MinervaRotationRefreshApplicationService($generationService, $regenerationService, $repository, new ArrayAdapter());

        $first = $service->refresh($from, $to, true);
        $second = $service->refresh($from, $to, true);

        self::assertSame($first, $second);
    }
}
