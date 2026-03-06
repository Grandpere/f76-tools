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

use Symfony\Component\Process\Process;

final class ProcessCommandRunner implements CommandRunner
{
    public function run(array $command, int $timeoutSeconds = 30): CommandExecutionResult
    {
        $process = new Process($command);
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return new CommandExecutionResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
