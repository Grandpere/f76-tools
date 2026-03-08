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
use Symfony\Component\HttpFoundation\RedirectResponse;
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
        $path = $request->getPathInfo();
        $queryLocale = $request->query->get('locale');

        $routeLocale = $request->attributes->get('_locale');
        if (
            is_string($routeLocale)
            && $this->isAllowedLocale($routeLocale)
            && is_string($queryLocale)
            && $this->isAllowedLocale($queryLocale)
            && strtolower(trim($queryLocale)) !== strtolower(trim($routeLocale))
        ) {
            $normalizedQueryLocale = strtolower(trim($queryLocale));
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_KEY, $normalizedQueryLocale);
            }

            $query = $request->query->all();
            unset($query['locale']);
            $queryString = http_build_query($query);
            $strippedPath = (string) preg_replace('#^/(en|fr|de)(?=/|$)#', '', $path);
            $target = sprintf('/%s%s', $normalizedQueryLocale, '' === $strippedPath ? '/' : $strippedPath);
            if ('' !== $queryString) {
                $target .= '?'.$queryString;
            }

            $event->setResponse(new RedirectResponse($target));

            return;
        }

        if (is_string($routeLocale) && $this->isAllowedLocale($routeLocale)) {
            $request->setLocale($routeLocale);
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_KEY, $routeLocale);
            }

            return;
        }

        if (is_string($queryLocale) && $this->isAllowedLocale($queryLocale)) {
            $normalizedQueryLocale = strtolower(trim($queryLocale));
            $request->setLocale($normalizedQueryLocale);
            if ($request->hasSession()) {
                $request->getSession()->set(self::SESSION_KEY, $normalizedQueryLocale);
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
