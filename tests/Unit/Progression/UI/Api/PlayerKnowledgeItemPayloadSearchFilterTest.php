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

use App\Progression\UI\Api\PlayerKnowledgeItemPayloadSearchFilter;
use PHPUnit\Framework\TestCase;

final class PlayerKnowledgeItemPayloadSearchFilterTest extends TestCase
{
    public function testFilterReturnsSamePayloadWhenQueryIsNullOrBlank(): void
    {
        $filter = new PlayerKnowledgeItemPayloadSearchFilter();
        $payload = [
            ['name' => 'Alpha', 'nameKey' => 'catalog.alpha', 'description' => null, 'descKey' => null],
            ['name' => 'Beta', 'nameKey' => 'catalog.beta', 'description' => null, 'descKey' => null],
        ];

        self::assertSame($payload, $filter->filter($payload, null));
        self::assertSame($payload, $filter->filter($payload, '   '));
        self::assertSame($payload, $filter->filter($payload, 123));
    }

    public function testFilterMatchesNameDescriptionAndKeysCaseInsensitive(): void
    {
        $filter = new PlayerKnowledgeItemPayloadSearchFilter();
        $payload = [
            ['name' => 'Alpha Plan', 'nameKey' => 'catalog.alpha.name', 'description' => 'Rare reward', 'descKey' => 'catalog.alpha.desc'],
            ['name' => 'Beta Mod', 'nameKey' => 'catalog.beta.name', 'description' => 'Legendary', 'descKey' => 'catalog.beta.desc'],
        ];

        $byName = $filter->filter($payload, 'alpha');
        self::assertCount(1, $byName);
        self::assertSame('Alpha Plan', $byName[0]['name']);

        $byDescription = $filter->filter($payload, 'legendary');
        self::assertCount(1, $byDescription);
        self::assertSame('Beta Mod', $byDescription[0]['name']);

        $byNameKey = $filter->filter($payload, 'CATALOG.BETA.NAME');
        self::assertCount(1, $byNameKey);
        self::assertSame('Beta Mod', $byNameKey[0]['name']);

        $byDescKey = $filter->filter($payload, 'alpha.desc');
        self::assertCount(1, $byDescKey);
        self::assertSame('Alpha Plan', $byDescKey[0]['name']);
    }
}
