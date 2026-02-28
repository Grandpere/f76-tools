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

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Security\ActiveUserSessionRegistry;
use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class ActiveSessionSubscriber implements EventSubscriberInterface
{
    private const SESSION_GUARD_INITIALIZED_KEY = 'identity.active_session_guard_initialized';

    public function __construct(
        private readonly ActiveUserSessionRegistry $activeUserSessionRegistry,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -64],
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $userId = $user->getId();
        if (!is_int($userId)) {
            return;
        }

        $session = $request->getSession();
        $sessionId = $session->getId();
        if ('' === trim($sessionId)) {
            $session->start();
            $sessionId = $session->getId();
        }

        if ('' === trim($sessionId)) {
            return;
        }

        // First authenticated request for this PHP session: initialize guard state.
        // This avoids false positive revocations in test/runtime environments where user IDs can be recycled.
        if (!$session->has(self::SESSION_GUARD_INITIALIZED_KEY)) {
            $this->activeUserSessionRegistry->registerOrTouch($userId, $sessionId, $request->getClientIp(), (string) $request->headers->get('User-Agent', ''), new DateTimeImmutable());
            $session->set(self::SESSION_GUARD_INITIALIZED_KEY, true);

            return;
        }

        if (!$this->activeUserSessionRegistry->hasSession($userId, $sessionId)) {
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('warning', 'security.session.flash.revoked');
            }

            $this->authEventLogger->warning('security.auth.session.revoked', $user->getEmail(), $request->getClientIp(), [
                'reason' => 'session_not_active',
            ]);

            $session->invalidate();
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', [
                'locale' => $request->getLocale(),
            ])));

            return;
        }

        $this->activeUserSessionRegistry->registerOrTouch($userId, $sessionId, $request->getClientIp(), (string) $request->headers->get('User-Agent', ''), new DateTimeImmutable());
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ('main' !== $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $userId = $user->getId();
        if (!is_int($userId)) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $sessionId = $request->getSession()->getId();
        if ('' === trim($sessionId)) {
            return;
        }

        $this->activeUserSessionRegistry->registerOrTouch($userId, $sessionId, $request->getClientIp(), (string) $request->headers->get('User-Agent', ''), new DateTimeImmutable());
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user instanceof UserEntity) {
            return;
        }

        $userId = $user->getId();
        if (!is_int($userId)) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $sessionId = $request->getSession()->getId();
        if ('' === trim($sessionId)) {
            return;
        }

        $this->activeUserSessionRegistry->revokeSession($userId, $sessionId);
    }
}
