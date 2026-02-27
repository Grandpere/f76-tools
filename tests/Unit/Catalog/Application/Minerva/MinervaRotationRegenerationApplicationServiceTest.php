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

use App\Catalog\Application\Minerva\MinervaRotationGenerationApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationRepository;
use App\Catalog\Domain\Entity\MinervaRotationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MinervaRotationRegenerationApplicationServiceTest extends TestCase
{
    public function testRegenerateSkipsGeneratedWindowsOverriddenByManualRows(): void
    {
        $generationService = new MinervaRotationGenerationApplicationService();
        $repository = $this->createMock(MinervaRotationRegenerationRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new MinervaRotationRegenerationApplicationService($generationService, $repository, $entityManager);

        $from = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
        $to = new DateTimeImmutable('2026-03-20T23:59:59+00:00');

        $repository
            ->expects(self::once())
            ->method('findManualOverlappingRange')
            ->with($from, $to)
            ->willReturn([
                new MinervaRotationEntity()
                    ->setLocation('Override')
                    ->setListCycle(99)
                    ->setStartsAt(new DateTimeImmutable('2026-03-01T00:00:00+00:00'))
                    ->setEndsAt(new DateTimeImmutable('2026-03-05T00:00:00+00:00')),
            ]);

        $repository
            ->expects(self::once())
            ->method('deleteOverlappingGeneratedRange')
            ->with($from, $to)
            ->willReturn(3);

        $entityManager->expects(self::exactly(2))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $result = $service->regenerate($from, $to);

        self::assertSame(3, $result['deleted']);
        self::assertSame(2, $result['inserted']);
        self::assertSame(1, $result['skipped']);
    }
}
