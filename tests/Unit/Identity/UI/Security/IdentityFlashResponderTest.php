<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityLocaleRedirector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IdentityFlashResponderTest extends TestCase
{
    public function testWarningToRouteAddsFlashAndRedirects(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_register', ['locale' => 'fr'])
            ->willReturn('/fr/register');

        $responder = new IdentityFlashResponder(new IdentityLocaleRedirector($urlGenerator));
        $request = Request::create('/fr/register');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->warningToRoute($request, 'app_register', 'security.register.flash.invalid_csrf');

        self::assertSame('/fr/register', $response->getTargetUrl());
        $flashBag = $request->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashBag);
        self::assertSame(['security.register.flash.invalid_csrf'], $flashBag->peek('warning'));
    }

    public function testFlashToCurrentUriRedirectsToCurrentUri(): void
    {
        $responder = new IdentityFlashResponder(new IdentityLocaleRedirector($this->createStub(UrlGeneratorInterface::class)));
        $request = Request::create('https://example.test/reset-password/token');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->flashToCurrentUri($request, 'success', 'security.reset.flash.success');

        self::assertSame('https://example.test/reset-password/token', $response->getTargetUrl());
        $flashBag = $request->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashBag);
        self::assertSame(['security.reset.flash.success'], $flashBag->peek('success'));
    }
}
