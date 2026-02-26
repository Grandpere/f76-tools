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
use PHPUnit\Framework\TestCase;

final class MinervaRotationGenerationApplicationServiceTest extends TestCase
{
    public function testGenerateBuildsDeterministicWindowsForKnownPeriod(): void
    {
        $service = new MinervaRotationGenerationApplicationService();
        $from = new \DateTimeImmutable('2026-03-01T00:00:00-05:00');
        $to = new \DateTimeImmutable('2026-03-20T23:59:59-04:00');

        $rows = $service->generate($from, $to);

        self::assertNotEmpty($rows);
        self::assertSame(3, $rows[0]['listCycle']);
        self::assertSame('Fort Atlas', $rows[0]['location']);
        self::assertSame('2026-03-02 12:00', $rows[0]['startsAt']->format('Y-m-d H:i'));
        self::assertSame('2026-03-04 12:00', $rows[0]['endsAt']->format('Y-m-d H:i'));

        self::assertSame(4, $rows[1]['listCycle']);
        self::assertSame('The Whitespring Resort', $rows[1]['location']);
        self::assertSame('2026-03-12 12:00', $rows[1]['startsAt']->format('Y-m-d H:i'));
        self::assertSame('2026-03-16 12:00', $rows[1]['endsAt']->format('Y-m-d H:i'));

        self::assertSame(5, $rows[2]['listCycle']);
        self::assertSame('Foundation', $rows[2]['location']);
    }

    public function testGenerateHandlesReverseRangeAsEmpty(): void
    {
        $service = new MinervaRotationGenerationApplicationService();
        $from = new \DateTimeImmutable('2026-03-10T00:00:00-04:00');
        $to = new \DateTimeImmutable('2026-03-01T00:00:00-05:00');

        self::assertSame([], $service->generate($from, $to));
    }
}
