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

namespace App\Tests\Unit\Catalog\Roadmap;

use App\Catalog\Application\Roadmap\RoadmapSeasonExtractor;
use PHPUnit\Framework\TestCase;

final class RoadmapSeasonExtractorTest extends TestCase
{
    public function testExtractsSeasonNumberFromFrenchText(): void
    {
        $extractor = new RoadmapSeasonExtractor();

        self::assertSame(24, $extractor->extractSeasonNumber("FORÊT SAUVAGE ET SAISON 24\nMARS"));
    }

    public function testExtractsSeasonNumberFromEnglishText(): void
    {
        $extractor = new RoadmapSeasonExtractor();

        self::assertSame(25, $extractor->extractSeasonNumber("UPCOMING UPDATE AND SEASON 25\nJUNE"));
    }

    public function testReturnsNullWhenSeasonMarkerMissing(): void
    {
        $extractor = new RoadmapSeasonExtractor();

        self::assertNull($extractor->extractSeasonNumber("COMMUNITY CALENDAR\nMARCH"));
    }
}
