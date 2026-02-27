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

use App\Catalog\Application\Minerva\MinervaRotationOverrideApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationRepository;
use App\Catalog\Domain\Entity\MinervaRotationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MinervaRotationOverrideApplicationServiceTest extends TestCase
{
    public function testCreateManualOverrideDeletesOverlappingGeneratedRowsAndPersists(): void
    {
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new MinervaRotationOverrideApplicationService($repository, $entityManager);

        $startsAt = new DateTimeImmutable('2026-04-01T12:00:00+00:00');
        $endsAt = new DateTimeImmutable('2026-04-03T12:00:00+00:00');

        $repository
            ->expects(self::once())
            ->method('deleteOverlappingGeneratedRange')
            ->with($startsAt, $endsAt)
            ->willReturn(1);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service->createManualOverride('Foundation', 9, $startsAt, $endsAt);
    }

    public function testDeleteManualOverrideReturnsFalseWhenNotFound(): void
    {
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new MinervaRotationOverrideApplicationService($repository, $entityManager);

        $repository->expects(self::once())->method('findManualById')->with(42)->willReturn(null);
        $entityManager->expects(self::never())->method('remove');

        self::assertFalse($service->deleteManualOverride(42));
    }

    public function testDeleteManualOverrideRemovesFoundRow(): void
    {
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new MinervaRotationOverrideApplicationService($repository, $entityManager);

        $row = new MinervaRotationEntity()
            ->setLocation('Fort Atlas')
            ->setListCycle(5)
            ->setStartsAt(new DateTimeImmutable('2026-04-01T12:00:00+00:00'))
            ->setEndsAt(new DateTimeImmutable('2026-04-03T12:00:00+00:00'));
        $repository->expects(self::once())->method('findManualById')->with(7)->willReturn($row);
        $entityManager->expects(self::once())->method('remove')->with($row);
        $entityManager->expects(self::once())->method('flush');

        self::assertTrue($service->deleteManualOverride(7));
    }
}
