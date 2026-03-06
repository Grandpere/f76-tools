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

namespace App\Catalog\Application\Roadmap\Ocr;

final class OcrQualityAssessor
{
    public function __construct(
        private readonly OcrQualityPolicy $policy = new OcrQualityPolicy(),
    ) {
    }

    public function assess(OcrResult $result): OcrQualityAssessment
    {
        $reasons = [];

        if ($result->confidence < $this->policy->minConfidence) {
            $reasons[] = sprintf('confidence %.4f below threshold %.4f', $result->confidence, $this->policy->minConfidence);
        }

        $nonEmptyLines = 0;
        foreach ($result->lines as $line) {
            if ('' !== trim($line)) {
                ++$nonEmptyLines;
            }
        }
        if ($nonEmptyLines < $this->policy->minNonEmptyLines) {
            $reasons[] = sprintf('non-empty lines %d below minimum %d', $nonEmptyLines, $this->policy->minNonEmptyLines);
        }

        return new OcrQualityAssessment([] === $reasons, $reasons);
    }
}
