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

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\ItemEntity;
use App\Progression\Application\Knowledge\ItemReadApplicationService;
use App\Progression\Application\Knowledge\ItemReadRepositoryInterface;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use App\Progression\UI\Api\ProgressionItemApiResolver;
use App\Progression\UI\Api\ProgressionItemApiResolverTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ProgressionItemApiResolverTraitTest extends TestCase
{
    public function testResolveItemOrNotFoundDelegatesToResolver(): void
    {
        $item = new ItemEntity();

        /** @var ItemReadRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ItemReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6')
            ->willReturn($item);

        $resolver = new ProgressionItemApiResolver(
            new ItemReadApplicationService($repository),
            new ProgressionApiErrorResponder(),
        );

        $helper = new class($resolver) {
            use ProgressionItemApiResolverTrait;

            public function __construct(private readonly ProgressionItemApiResolver $resolver)
            {
            }

            public function resolveItem(string $itemId): ItemEntity|JsonResponse
            {
                return $this->resolveItemOrNotFound($itemId);
            }

            protected function progressionItemApiResolver(): ProgressionItemApiResolver
            {
                return $this->resolver;
            }
        };

        $result = $helper->resolveItem('01J5A6B7C8D9E0F1G2H3J4K5L6');
        self::assertSame($item, $result);
    }

    public function testResolveItemOrNotFoundReturnsJson404WhenItemMissing(): void
    {
        /** @var ItemReadRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(ItemReadRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findOneByPublicId')
            ->with('01J5A6B7C8D9E0F1G2H3J4K5L6')
            ->willReturn(null);

        $resolver = new ProgressionItemApiResolver(
            new ItemReadApplicationService($repository),
            new ProgressionApiErrorResponder(),
        );

        $helper = new class($resolver) {
            use ProgressionItemApiResolverTrait;

            public function __construct(private readonly ProgressionItemApiResolver $resolver)
            {
            }

            public function resolveItem(string $itemId): ItemEntity|JsonResponse
            {
                return $this->resolveItemOrNotFound($itemId);
            }

            protected function progressionItemApiResolver(): ProgressionItemApiResolver
            {
                return $this->resolver;
            }
        };

        $result = $helper->resolveItem('01J5A6B7C8D9E0F1G2H3J4K5L6');
        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(JsonResponse::HTTP_NOT_FOUND, $result->getStatusCode());
        self::assertSame('{"error":"Item not found."}', $result->getContent());
    }
}
