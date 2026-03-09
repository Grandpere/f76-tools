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

namespace App\Tests\Unit\Support\Infrastructure\Http;

use App\Support\Infrastructure\Http\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class SecurityHeadersSubscriberTest extends TestCase
{
    public function testAddsDefaultSecurityHeaders(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');
        $event = $this->responseEvent('http://localhost/', true);

        $subscriber->onKernelResponse($event);

        $headers = $event->getResponse()->headers;
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertSame('DENY', $headers->get('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $headers->get('Referrer-Policy'));
        self::assertSame('geolocation=(), microphone=(), camera=()', $headers->get('Permissions-Policy'));
        self::assertNotNull($headers->get('Content-Security-Policy-Report-Only'));
        self::assertFalse($headers->has('Strict-Transport-Security'));
    }

    public function testAddsHstsOnlyInProdAndSecureRequest(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $event = $this->responseEvent('https://example.org/', true);

        $subscriber->onKernelResponse($event);

        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $event->getResponse()->headers->get('Strict-Transport-Security')
        );
    }

    public function testDoesNotAddHstsInProdWhenRequestIsNotSecure(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');
        $event = $this->responseEvent('http://example.org/', true);

        $subscriber->onKernelResponse($event);

        self::assertFalse($event->getResponse()->headers->has('Strict-Transport-Security'));
    }

    private function responseEvent(string $url, bool $mainRequest): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($url);
        $response = new Response();

        return new ResponseEvent(
            $kernel,
            $request,
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response,
        );
    }
}

