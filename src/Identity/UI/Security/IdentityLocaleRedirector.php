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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IdentityLocaleRedirector
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function toRouteWithRequestLocale(Request $request, string $routeName, array $parameters = []): RedirectResponse
    {
        if (!array_key_exists('locale', $parameters)) {
            $parameters['locale'] = $request->getLocale();
        }

        return new RedirectResponse($this->urlGenerator->generate($routeName, $parameters));
    }

    public function toLogin(Request $request): RedirectResponse
    {
        return $this->toRouteWithRequestLocale($request, 'app_login');
    }
}
