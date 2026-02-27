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

namespace App\Tests\Unit\Support\Application\Contact;

use App\Support\Application\Contact\ContactMessageListApplicationService;
use App\Support\Application\Contact\ContactMessageListQuery;
use App\Support\Application\Contact\ContactMessageReadRepositoryInterface;
use App\Support\Domain\Contact\ContactMessageStatusEnum;
use PHPUnit\Framework\TestCase;

final class ContactMessageListApplicationServiceTest extends TestCase
{
    public function testListSanitizesInputsAndReturnsResult(): void
    {
        $repository = new InMemoryContactMessageReadRepository([
            ['rows' => [], 'total' => 15],
        ]);

        $service = new ContactMessageListApplicationService($repository);
        $result = $service->list(ContactMessageListQuery::fromRaw('  hello ', ContactMessageStatusEnum::NEW->value, '2', '40'));

        self::assertSame('hello', $result->query);
        self::assertSame(ContactMessageStatusEnum::NEW, $result->status);
        self::assertSame(1, $result->page);
        self::assertSame(40, $result->perPage);
        self::assertSame(1, $result->totalPages);
        self::assertCount(2, $repository->calls);
        self::assertSame(['hello', ContactMessageStatusEnum::NEW, 2, 40], $repository->calls[0]);
        self::assertSame(['hello', ContactMessageStatusEnum::NEW, 1, 40], $repository->calls[1]);
    }

    public function testListRequestsLastPageWhenRequestedPageExceedsTotalPages(): void
    {
        $repository = new InMemoryContactMessageReadRepository([
            ['rows' => [], 'total' => 31],
            ['rows' => [], 'total' => 31],
        ]);

        $service = new ContactMessageListApplicationService($repository);
        $result = $service->list(ContactMessageListQuery::fromRaw('', '', '99', '10'));

        self::assertSame(4, $result->page);
        self::assertSame(4, $result->totalPages);
        self::assertCount(2, $repository->calls);
        self::assertSame(['', null, 99, 10], $repository->calls[0]);
        self::assertSame(['', null, 4, 10], $repository->calls[1]);
    }

    public function testListFallsBackToDefaultsForInvalidValues(): void
    {
        $repository = new InMemoryContactMessageReadRepository([
            ['rows' => [], 'total' => 0],
        ]);

        $service = new ContactMessageListApplicationService($repository);
        $result = $service->list(ContactMessageListQuery::fromRaw(null, 'invalid', 'abc', '-5'));

        self::assertSame('', $result->query);
        self::assertNull($result->status);
        self::assertSame(1, $result->page);
        self::assertSame(30, $result->perPage);
        self::assertSame(1, $result->totalPages);
        self::assertSame(['', null, 1, 30], $repository->calls[0]);
    }
}

/**
 * @internal
 */
final class InMemoryContactMessageReadRepository implements ContactMessageReadRepositoryInterface
{
    /**
     * @var list<array{rows: list<\App\Support\Domain\Entity\ContactMessageEntity>, total: int}>
     */
    private array $results;

    /**
     * @var list<array{0: string, 1: ?ContactMessageStatusEnum, 2: int, 3: int}>
     */
    public array $calls = [];

    /**
     * @param list<array{rows: list<\App\Support\Domain\Entity\ContactMessageEntity>, total: int}> $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    public function findPaginated(string $query, ?ContactMessageStatusEnum $status, int $page, int $perPage): array
    {
        $this->calls[] = [$query, $status, $page, $perPage];

        $result = array_shift($this->results);
        if (!is_array($result)) {
            return ['rows' => [], 'total' => 0];
        }

        return $result;
    }
}
