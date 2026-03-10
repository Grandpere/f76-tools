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

namespace App\Tests\Unit\Catalog\Roadmap\Ocr;

use App\Catalog\Application\Roadmap\Ocr\OcrQualityAssessor;
use App\Catalog\Application\Roadmap\Ocr\OcrQualityPolicy;
use App\Catalog\Application\Roadmap\Ocr\OcrResult;
use PHPUnit\Framework\TestCase;

final class OcrQualityAssessorTest extends TestCase
{
    public function testAssessmentIsAcceptableWhenConfidenceAndLinesMeetThreshold(): void
    {
        $assessor = new OcrQualityAssessor(new OcrQualityPolicy(0.90, 3));
        $result = new OcrResult(
            'tesseract',
            "Line A\nLine B\nLine C",
            0.92,
            ['Line A', 'Line B', 'Line C'],
        );

        $assessment = $assessor->assess($result);

        self::assertTrue($assessment->acceptable);
        self::assertSame([], $assessment->reasons);
    }

    public function testAssessmentIsRejectedWhenConfidenceIsBelowThreshold(): void
    {
        $assessor = new OcrQualityAssessor(new OcrQualityPolicy(0.90, 3));
        $result = new OcrResult(
            'tesseract',
            "Line A\nLine B\nLine C",
            0.71,
            ['Line A', 'Line B', 'Line C'],
        );

        $assessment = $assessor->assess($result);

        self::assertFalse($assessment->acceptable);
        self::assertCount(1, $assessment->reasons);
        self::assertStringContainsString('confidence', $assessment->reasons[0]);
    }

    public function testAssessmentIsRejectedWhenNonEmptyLinesAreInsufficient(): void
    {
        $assessor = new OcrQualityAssessor(new OcrQualityPolicy(0.90, 3));
        $result = new OcrResult(
            'ocr.space',
            "Line A\n\n ",
            0.95,
            ['Line A', '', ' '],
        );

        $assessment = $assessor->assess($result);

        self::assertFalse($assessment->acceptable);
        self::assertCount(1, $assessment->reasons);
        self::assertStringContainsString('non-empty lines', $assessment->reasons[0]);
    }

    public function testAssessmentContainsTwoReasonsWhenConfidenceAndLinesFail(): void
    {
        $assessor = new OcrQualityAssessor(new OcrQualityPolicy(0.90, 3));
        $result = new OcrResult(
            'ocr.space',
            'Single line',
            0.44,
            ['Single line'],
        );

        $assessment = $assessor->assess($result);

        self::assertFalse($assessment->acceptable);
        self::assertCount(2, $assessment->reasons);
        self::assertStringContainsString('confidence', $assessment->reasons[0]);
        self::assertStringContainsString('non-empty lines', $assessment->reasons[1]);
    }
}

