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

namespace App\Tests\Unit\Catalog\Application\Translation;

use App\Catalog\Application\Translation\ItemTranslationListQuery;
use PHPUnit\Framework\TestCase;

final class ItemTranslationListQueryTest extends TestCase
{
    public function testFromRawSanitizesLockedLocaleAndPaginationBounds(): void
    {
        $query = ItemTranslationListQuery::fromRaw(
            'en',
            '  Plan FR  ',
            0,
            9999,
        );

        self::assertSame('fr', $query->targetLocale);
        self::assertSame('plan fr', $query->query);
        self::assertSame(1, $query->page);
        self::assertSame(200, $query->perPage);
    }

    public function testFromRawAcceptsCustomLocaleAndNullQueryWhenEmpty(): void
    {
        $query = ItemTranslationListQuery::fromRaw(
            'it',
            '   ',
            3,
            25,
        );

        self::assertSame('it', $query->targetLocale);
        self::assertNull($query->query);
        self::assertSame(3, $query->page);
        self::assertSame(25, $query->perPage);
    }

    public function testFromRawFallsBackWhenLocaleFormatIsInvalid(): void
    {
        $query = ItemTranslationListQuery::fromRaw(
            'invalid-locale',
            null,
            null,
            null,
        );

        self::assertSame('fr', $query->targetLocale);
        self::assertNull($query->query);
        self::assertSame(1, $query->page);
        self::assertSame(40, $query->perPage);
    }
}
