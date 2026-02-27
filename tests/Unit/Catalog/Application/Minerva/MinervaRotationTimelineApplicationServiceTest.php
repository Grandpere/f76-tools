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

use App\Catalog\Application\Minerva\MinervaRotationReader;
use App\Catalog\Application\Minerva\MinervaRotationTimelineApplicationService;
use App\Catalog\Domain\Entity\MinervaRotationEntity;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MinervaRotationTimelineApplicationServiceTest extends TestCase
{
    public function testBuildTimelineResolvesUpcomingActiveAndEndedStatuses(): void
    {
        /** @var MinervaRotationReader&MockObject $reader */
        $reader = $this->createMock(MinervaRotationReader::class);
        $service = new MinervaRotationTimelineApplicationService($reader);

        $reader
            ->expects(self::once())
            ->method('findAllOrdered')
            ->willReturn([
                $this->createRotation(1, 'Foundation', 5, '2026-02-28T10:00:00+00:00', '2026-03-01T10:00:00+00:00'),
                $this->createRotation(2, 'Crater', 6, '2026-02-26T10:00:00+00:00', '2026-02-27T10:00:00+00:00'),
                $this->createRotation(3, 'Fort Atlas', 4, '2026-02-20T10:00:00+00:00', '2026-02-21T10:00:00+00:00'),
            ]);

        $timeline = $service->buildTimeline(new DateTimeImmutable('2026-02-26T12:00:00+00:00'));

        self::assertSame('UTC', $timeline['timezone']);
        self::assertCount(3, $timeline['rows']);
        self::assertSame('upcoming', $timeline['rows'][0]['status']);
        self::assertSame('active', $timeline['rows'][1]['status']);
        self::assertSame('ended', $timeline['rows'][2]['status']);
        self::assertIsArray($timeline['current']);
        self::assertSame('Crater', $timeline['current']['location']);
        self::assertCount(1, $timeline['upcoming']);
        self::assertSame('Foundation', $timeline['upcoming'][0]['location']);
    }

    private function createRotation(
        int $id,
        string $location,
        int $listCycle,
        string $startsAt,
        string $endsAt,
    ): MinervaRotationEntity {
        $rotation = new MinervaRotationEntity()
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt));

        $reflection = new ReflectionClass($rotation);
        $property = $reflection->getProperty('id');
        $property->setValue($rotation, $id);

        return $rotation;
    }
}
