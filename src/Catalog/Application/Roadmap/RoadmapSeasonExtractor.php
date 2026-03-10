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

namespace App\Catalog\Application\Roadmap;

final class RoadmapSeasonExtractor
{
    public function extractSeasonNumber(string $rawText): ?int
    {
        $normalized = $this->normalize($rawText);
        if ('' === $normalized) {
            return null;
        }

        if (1 !== preg_match('/\b(?:SAISON|SEASON)\s*([0-9]{1,3})\b/u', $normalized, $matches)) {
            return null;
        }

        $value = (int) $matches[1];

        return $value > 0 ? $value : null;
    }

    private function normalize(string $rawText): string
    {
        $upper = mb_strtoupper($rawText, 'UTF-8');
        $ascii = strtr($upper, [
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'Ç' => 'C',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I',
            'Ô' => 'O', 'Ö' => 'O',
            'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ÿ' => 'Y',
            'ß' => 'SS',
        ]);

        $collapsed = preg_replace('/\s+/u', ' ', $ascii);

        return is_string($collapsed) ? trim($collapsed) : '';
    }
}
