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

namespace App\Tests\Unit\Progression\Application\Knowledge;

use App\Catalog\Domain\Entity\ItemEntity;
use App\Progression\Application\Knowledge\ItemReadApplicationService;
use App\Progression\Application\Knowledge\ItemReadRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ItemReadApplicationServiceTest extends TestCase
{
    public function testFindByPublicIdDelegatesToRepository(): void
    {
        $item = new ItemEntity();

        /** @var ItemReadRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ItemReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6')
            ->willReturn($item);

        $service = new ItemReadApplicationService($repository);
        $result = $service->findByPublicId('01J5A6B7C8D9E0F1G2H3J4K5L6');

        self::assertSame($item, $result);
    }
}
