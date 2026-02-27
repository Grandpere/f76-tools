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

namespace App\Support\Infrastructure\Http;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class LocaleSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = '_locale';
    private const ALLOWED_LOCALES = ['en', 'de', 'fr'];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $queryLocale = $request->query->get('locale');

        if (is_string($queryLocale) && $this->isAllowedLocale($queryLocale)) {
            $request->setLocale($queryLocale);
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_KEY, $queryLocale);
            }

            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $sessionLocale = $request->getSession()->get(self::SESSION_KEY);
        if (is_string($sessionLocale) && $this->isAllowedLocale($sessionLocale)) {
            $request->setLocale($sessionLocale);
        }
    }

    private function isAllowedLocale(string $locale): bool
    {
        return in_array(strtolower(trim($locale)), self::ALLOWED_LOCALES, true);
    }
}
