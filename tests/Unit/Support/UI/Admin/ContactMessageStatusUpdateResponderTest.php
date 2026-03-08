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

namespace App\Tests\Unit\Support\UI\Admin;

use App\Support\Application\Contact\ContactMessageStatusUpdateResult;
use App\Support\UI\Admin\ContactMessageStatusUpdateFeedbackMapper;
use App\Support\UI\Admin\ContactMessageStatusUpdateResponder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ContactMessageStatusUpdateResponderTest extends TestCase
{
    public function testInvalidCsrfAddsWarningFlashAndRedirects(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_admin_contact_messages', ['_locale' => 'fr'])
            ->willReturn('/fr/admin/contact-messages');

        $responder = new ContactMessageStatusUpdateResponder(
            new ContactMessageStatusUpdateFeedbackMapper(),
            $urlGenerator,
        );

        $request = Request::create('/fr/admin/contact-messages');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->invalidCsrf($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fr/admin/contact-messages', $response->getTargetUrl());
    }

    public function testFromResultAddsMappedFlashAndRedirects(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('app_admin_contact_messages', ['_locale' => 'fr'])
            ->willReturn('/fr/admin/contact-messages');

        $responder = new ContactMessageStatusUpdateResponder(
            new ContactMessageStatusUpdateFeedbackMapper(),
            $urlGenerator,
        );

        $request = Request::create('/fr/admin/contact-messages');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $responder->fromResult($request, ContactMessageStatusUpdateResult::UPDATED);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/fr/admin/contact-messages', $response->getTargetUrl());
    }
}
