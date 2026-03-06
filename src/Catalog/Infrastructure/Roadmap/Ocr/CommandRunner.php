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

namespace App\Catalog\Infrastructure\Roadmap\Ocr;

interface CommandRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, int $timeoutSeconds = 30): CommandExecutionResult;
}
