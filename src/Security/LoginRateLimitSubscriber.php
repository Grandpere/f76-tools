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

namespace App\Security;

use App\Entity\UserEntity;
use App\Service\AuthRequestThrottler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginRateLimitSubscriber implements EventSubscriberInterface
{
    private const SCOPE = 'login';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300;

    public function __construct(
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 300],
            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('POST') || '/login' !== $request->getPathInfo()) {
            return;
        }

        $email = mb_strtolower(trim((string) $request->request->get('_username', '')));
        if (!$this->requestThrottler->isLimited(self::SCOPE, $request->getClientIp(), $email, self::MAX_ATTEMPTS)) {
            return;
        }

        $this->authEventLogger->warning('security.auth.login.rate_limited', $email, $request->getClientIp(), [
            'scope' => self::SCOPE,
            'maxAttempts' => self::MAX_ATTEMPTS,
            'windowSeconds' => self::WINDOW_SECONDS,
        ]);

        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', 'security.auth.flash.rate_limited');
            }
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', [
            'locale' => $request->getLocale(),
        ])));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ('main' !== $event->getFirewallName()) {
            return;
        }

        $request = $event->getRequest();
        $email = mb_strtolower(trim((string) $request->request->get('_username', '')));

        $this->requestThrottler->hit(self::SCOPE, $request->getClientIp(), $email, self::WINDOW_SECONDS);
        $this->authEventLogger->warning('security.auth.login.failed', $email, $request->getClientIp(), [
            'scope' => self::SCOPE,
        ]);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ('main' !== $event->getFirewallName()) {
            return;
        }

        $request = $event->getRequest();
        $user = $event->getUser();
        $email = $user instanceof UserEntity ? $user->getEmail() : $user->getUserIdentifier();

        $this->requestThrottler->clear(self::SCOPE, $request->getClientIp(), $email);
        $this->authEventLogger->info('security.auth.login.success', $email, $request->getClientIp(), [
            'scope' => self::SCOPE,
        ]);
    }
}
