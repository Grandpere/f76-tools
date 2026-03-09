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

namespace App\Tests\Unit\Support\Infrastructure\Security;

use App\Support\Infrastructure\Security\AppSecretGuardSubscriber;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class AppSecretGuardSubscriberTest extends TestCase
{
    public function testDoesNothingOutsideProd(): void
    {
        $subscriber = new AppSecretGuardSubscriber('', 'dev');
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onConsoleCommand($this->consoleEvent());

        $this->addToAssertionCount(1);
    }

    public function testThrowsInProdWhenSecretIsEmptyForMainRequest(): void
    {
        $subscriber = new AppSecretGuardSubscriber('', 'prod');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET must be configured');
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
    }

    public function testDoesNotThrowInProdForSubRequest(): void
    {
        $subscriber = new AppSecretGuardSubscriber('', 'prod');
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::SUB_REQUEST));

        $this->addToAssertionCount(1);
    }

    public function testThrowsInProdWhenSecretIsEmptyForConsole(): void
    {
        $subscriber = new AppSecretGuardSubscriber('', 'prod');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET must be configured');
        $subscriber->onConsoleCommand($this->consoleEvent());
    }

    public function testDoesNotThrowInProdWhenSecretIsConfigured(): void
    {
        $subscriber = new AppSecretGuardSubscriber('configured-secret', 'prod');
        $subscriber->onKernelRequest($this->requestEvent(HttpKernelInterface::MAIN_REQUEST));
        $subscriber->onConsoleCommand($this->consoleEvent());

        $this->addToAssertionCount(1);
    }

    private function requestEvent(int $requestType): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('https://example.org/');

        return new RequestEvent($kernel, $request, $requestType);
    }

    private function consoleEvent(): ConsoleCommandEvent
    {
        $command = new class extends Command {
        };

        return new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
    }
}
