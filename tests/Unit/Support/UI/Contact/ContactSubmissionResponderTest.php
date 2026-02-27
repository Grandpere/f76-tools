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

namespace App\Tests\Unit\Support\UI\Contact;

use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityLocaleRedirector;
use App\Security\AuthEventLogger;
use App\Support\Application\Contact\ContactSubmissionStatus;
use App\Support\UI\Contact\ContactSubmissionResponder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContactSubmissionResponderTest extends TestCase
{
    public function testGuardFailedReturnsWarningRedirect(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $logger->expects(self::never())->method('info');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_contact', ['locale' => 'fr'])
            ->willReturn('/fr/contact');

        $responder = new ContactSubmissionResponder(
            new AuthEventLogger($logger),
            new IdentityFlashResponder(new IdentityLocaleRedirector($urlGenerator)),
        );

        $request = Request::create('/fr/contact');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->guardFailed($request, IdentityEmailFlow::CONTACT, 'security.auth.flash.rate_limited');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fr/contact', $response->getTargetUrl());
    }

    public function testInvalidPayloadReturnsWarningRedirectAndLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_contact', ['locale' => 'fr'])
            ->willReturn('/fr/contact');

        $responder = new ContactSubmissionResponder(
            new AuthEventLogger($logger),
            new IdentityFlashResponder(new IdentityLocaleRedirector($urlGenerator)),
        );

        $request = Request::create('/fr/contact');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->invalidPayload($request, IdentityEmailFlow::CONTACT, 'visitor@example.com');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fr/contact', $response->getTargetUrl());
    }

    public function testSubmittedWithDeliveryFailureLogsWarningAndInfo(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $logger->expects(self::once())->method('info');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_contact', ['locale' => 'fr'])
            ->willReturn('/fr/contact');

        $responder = new ContactSubmissionResponder(
            new AuthEventLogger($logger),
            new IdentityFlashResponder(new IdentityLocaleRedirector($urlGenerator)),
        );

        $request = Request::create('/fr/contact');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->submitted(
            $request,
            IdentityEmailFlow::CONTACT,
            'visitor@example.com',
            ContactSubmissionStatus::SENT_WITH_DELIVERY_FAILURE,
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fr/contact', $response->getTargetUrl());
    }
}
