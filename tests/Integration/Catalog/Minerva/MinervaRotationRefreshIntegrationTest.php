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

namespace App\Tests\Integration\Catalog\Minerva;

use App\Catalog\Application\Minerva\MinervaRotationRefresher;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MinervaRotationRefreshIntegrationTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?MinervaRotationRefresher $refresher = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $refresher = $container->get(MinervaRotationRefresher::class);
        \assert($refresher instanceof MinervaRotationRefresher);
        $this->refresher = $refresher;

        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->refresher = null;
    }

    public function testRefreshIsIdempotentAcrossTwoExecutions(): void
    {
        $from = new DateTimeImmutable('2026-03-01 00:00:00');
        $to = new DateTimeImmutable('2026-03-20 23:59:59');

        $first = $this->refresher()->refresh($from, $to, false);
        self::assertTrue($first['performed']);
        self::assertGreaterThan(0, $first['inserted']);

        $countAfterFirst = $this->countBySource(MinervaRotationSourceEnum::GENERATED);
        self::assertGreaterThan(0, $countAfterFirst);

        $second = $this->refresher()->refresh($from, $to, false);
        self::assertFalse($second['performed']);
        self::assertSame(0, $second['inserted']);
        self::assertSame(0, $second['deleted']);
        self::assertSame(0, $second['missingWindows']);

        $countAfterSecond = $this->countBySource(MinervaRotationSourceEnum::GENERATED);
        self::assertSame($countAfterFirst, $countAfterSecond);
    }

    private function truncateTables(): void
    {
        $this->entityManager()?->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation RESTART IDENTITY CASCADE');
    }

    private function countBySource(MinervaRotationSourceEnum $source): int
    {
        $result = $this->entityManager()?->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM minerva_rotation WHERE source = :source',
            ['source' => $source->value],
        );

        if (is_int($result) || is_numeric($result)) {
            return (int) $result;
        }

        return 0;
    }

    private function entityManager(): ?EntityManagerInterface
    {
        return $this->entityManager;
    }

    private function refresher(): MinervaRotationRefresher
    {
        if (null === $this->refresher) {
            throw new LogicException('Minerva refresher is not initialized.');
        }

        return $this->refresher;
    }
}
