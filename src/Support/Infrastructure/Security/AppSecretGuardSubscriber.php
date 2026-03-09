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

namespace App\Support\Infrastructure\Security;

use RuntimeException;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AppSecretGuardSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $appSecret,
        private readonly string $appEnv,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 500],
            ConsoleEvents::COMMAND => 'onConsoleCommand',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->assertSecretIsConfigured();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->assertSecretIsConfigured();
    }

    private function assertSecretIsConfigured(): void
    {
        if ('prod' !== $this->appEnv) {
            return;
        }

        if ('' === trim($this->appSecret)) {
            throw new RuntimeException('APP_SECRET must be configured and non-empty in production.');
        }
    }
}

