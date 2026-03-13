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

final readonly class RoadmapParsedEventsValidationResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public array $errors,
        public array $warnings,
    ) {
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }
}
