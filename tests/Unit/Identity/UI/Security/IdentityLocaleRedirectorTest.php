<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\UI\Security\IdentityLocaleRedirector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IdentityLocaleRedirectorTest extends TestCase
{
    public function testToRouteWithRequestLocaleInjectsLocaleParameter(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_login', ['locale' => 'de'])
            ->willReturn('/de/login');

        $redirector = new IdentityLocaleRedirector($urlGenerator);
        $request = Request::create('/de/login');
        $request->setLocale('de');

        $response = $redirector->toRouteWithRequestLocale($request, 'app_login');

        self::assertSame('/de/login', $response->getTargetUrl());
    }

    public function testToRouteWithRequestLocaleKeepsProvidedLocale(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_register', ['locale' => 'en'])
            ->willReturn('/en/register');

        $redirector = new IdentityLocaleRedirector($urlGenerator);
        $request = Request::create('/fr/register');
        $request->setLocale('fr');

        $response = $redirector->toRouteWithRequestLocale($request, 'app_register', ['locale' => 'en']);

        self::assertSame('/en/register', $response->getTargetUrl());
    }

    public function testToLoginRedirectsToLoginRouteWithRequestLocale(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_login', ['locale' => 'fr'])
            ->willReturn('/fr/login');

        $redirector = new IdentityLocaleRedirector($urlGenerator);
        $request = Request::create('/fr/verify-email/token');
        $request->setLocale('fr');

        $response = $redirector->toLogin($request);

        self::assertSame('/fr/login', $response->getTargetUrl());
    }
}
