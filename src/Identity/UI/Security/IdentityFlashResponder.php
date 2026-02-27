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

namespace App\Identity\UI\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class IdentityFlashResponder
{
    public function __construct(
        private readonly IdentityLocaleRedirector $identityLocaleRedirector,
    ) {
    }

    public function warningToRoute(Request $request, string $routeName, string $flashMessage): RedirectResponse
    {
        return $this->flashToRoute($request, $routeName, 'warning', $flashMessage);
    }

    public function successToRoute(Request $request, string $routeName, string $flashMessage): RedirectResponse
    {
        return $this->flashToRoute($request, $routeName, 'success', $flashMessage);
    }

    public function warningToLogin(Request $request, string $flashMessage): RedirectResponse
    {
        return $this->flashToLogin($request, 'warning', $flashMessage);
    }

    public function successToLogin(Request $request, string $flashMessage): RedirectResponse
    {
        return $this->flashToLogin($request, 'success', $flashMessage);
    }

    public function flashToLogin(Request $request, string $flashType, string $flashMessage): RedirectResponse
    {
        $this->addFlashIfPossible($request, $flashType, $flashMessage);

        return $this->identityLocaleRedirector->toLogin($request);
    }

    public function flashToRoute(Request $request, string $routeName, string $flashType, string $flashMessage): RedirectResponse
    {
        $this->addFlashIfPossible($request, $flashType, $flashMessage);

        return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, $routeName);
    }

    public function flashToCurrentUri(Request $request, string $flashType, string $flashMessage): RedirectResponse
    {
        $this->addFlashIfPossible($request, $flashType, $flashMessage);

        return new RedirectResponse($request->getUri());
    }

    private function addFlashIfPossible(Request $request, string $flashType, string $flashMessage): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $flashBag = $request->getSession()->getBag('flashes');
        if (!$flashBag instanceof FlashBagInterface) {
            return;
        }

        $flashBag->add($flashType, $flashMessage);
    }
}
