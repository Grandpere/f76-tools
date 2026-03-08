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

final class AdminQueryStateSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 25],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('GET' !== strtoupper($request->getMethod()) || !$request->hasSession()) {
            return;
        }

        $routeValue = $request->attributes->get('_route');
        if (!is_string($routeValue) || !str_starts_with($routeValue, 'app_admin_')) {
            return;
        }
        $route = $routeValue;

        $stateKey = 'admin.query_state.'.$this->routeGroup($route);
        $query = $request->query->all();

        if ([] !== $query) {
            $request->getSession()->set($stateKey, $query);
            $event->setResponse(new RedirectResponse($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo()));

            return;
        }

        $stored = $request->getSession()->get($stateKey);
        if (is_array($stored) && [] !== $stored) {
            /** @var array<string, mixed> $stored */
            $request->query->replace($stored);
        }
    }

    private function routeGroup(string $route): string
    {
        return match (true) {
            str_starts_with($route, 'app_admin_users_auth_events') => 'users_auth_events',
            str_starts_with($route, 'app_admin_users') => 'users',
            str_starts_with($route, 'app_admin_audit_logs') => 'audit_logs',
            str_starts_with($route, 'app_admin_contact_messages') => 'contact_messages',
            str_starts_with($route, 'app_admin_item_translations') => 'item_translations',
            str_starts_with($route, 'app_admin_minerva_rotation') => 'minerva_rotation',
            str_starts_with($route, 'app_admin_roadmap') => 'roadmap',
            default => $route,
        };
    }
}
