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
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const SENSITIVE_PATH_PATTERN = '#^/(?:(?:en|fr|de)/)?(?:admin(?:/|$)|account-security(?:/|$)|change-password(?:/|$)|reset-password(?:/|$)|verify-email(?:/|$)|login(?:/|$)|register(?:/|$)|forgot-password(?:/|$)|resend-verification(?:/|$)|contact(?:/|$))#';
    private const CSP_POLICY = "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data: blob:; font-src 'self' data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; connect-src 'self' https://challenges.cloudflare.com";

    public function __construct(
        private readonly string $appEnv,
        private readonly string $cspMode = 'report_only',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'DENY');
        }
        if (!$headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if (!$headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }
        if (!$headers->has('Cross-Origin-Opener-Policy')) {
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        }
        if (!$headers->has('Cross-Origin-Resource-Policy')) {
            $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        }
        if (!$headers->has('X-Permitted-Cross-Domain-Policies')) {
            $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        }
        $this->applyCspHeader($headers);

        if ('prod' === $this->appEnv && $event->getRequest()->isSecure() && !$headers->has('Strict-Transport-Security')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $path = $event->getRequest()->getPathInfo();
        if (1 === preg_match(self::SENSITIVE_PATH_PATTERN, $path)) {
            $headers->set('Cache-Control', 'no-store, private, max-age=0');
            $headers->set('Pragma', 'no-cache');
            $headers->set('Expires', '0');
            $headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    private function applyCspHeader(\Symfony\Component\HttpFoundation\ResponseHeaderBag $headers): void
    {
        $mode = mb_strtolower(trim($this->cspMode));
        if ('off' === $mode) {
            return;
        }

        if ('enforce' === $mode) {
            if (!$headers->has('Content-Security-Policy')) {
                $headers->set('Content-Security-Policy', self::CSP_POLICY);
            }

            return;
        }

        if (!$headers->has('Content-Security-Policy-Report-Only')) {
            $headers->set('Content-Security-Policy-Report-Only', self::CSP_POLICY);
        }
    }
}
