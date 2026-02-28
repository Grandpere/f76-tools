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

namespace App\Identity\UI\Security\Controller;

use App\Identity\Application\Oidc\GoogleOidcAuthenticateApplicationService;
use App\Identity\Application\Oidc\GoogleOidcAuthenticationAction;
use App\Identity\Application\Oidc\GoogleOidcAuthenticationException;
use App\Identity\Application\Oidc\GoogleOidcClient;
use App\Identity\Application\Oidc\GoogleOidcConfig;
use App\Identity\Application\Oidc\GoogleOidcProviderException;
use App\Identity\Application\Security\AuthEventLogger;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentityLocaleRedirector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GoogleOidcController extends AbstractController
{
    private const STATE_SESSION_KEY = 'identity.oidc.google.state';
    private const NONCE_SESSION_KEY = 'identity.oidc.google.nonce';
    private const CODE_VERIFIER_SESSION_KEY = 'identity.oidc.google.code_verifier';
    private const ISSUED_AT_SESSION_KEY = 'identity.oidc.google.issued_at';
    private const STATE_TTL_SECONDS = 300;

    public function __construct(
        private readonly GoogleOidcConfig $googleOidcConfig,
        private readonly GoogleOidcClient $googleOidcClient,
        private readonly GoogleOidcAuthenticateApplicationService $googleOidcAuthenticateApplicationService,
        private readonly Security $security,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentityLocaleRedirector $identityLocaleRedirector,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/auth/google/start', name: 'app_oidc_google_start', methods: ['GET'])]
    public function start(Request $request): Response
    {
        if (!$this->googleOidcConfig->isEnabled()) {
            return $this->failedStart($request, 'security.oidc.flash.provider_disabled', 'security.auth.oidc.google.start_disabled');
        }

        if (!$request->hasSession()) {
            return $this->failedStart($request, 'security.oidc.flash.callback_failed', 'security.auth.oidc.google.session_missing');
        }

        $state = $this->randomUrlSafe();
        $nonce = $this->randomUrlSafe();
        $codeVerifier = $this->randomUrlSafe();
        $codeChallenge = $this->base64Url(hash('sha256', $codeVerifier, true));

        $session = $request->getSession();
        $session->set(self::STATE_SESSION_KEY, $state);
        $session->set(self::NONCE_SESSION_KEY, $nonce);
        $session->set(self::CODE_VERIFIER_SESSION_KEY, $codeVerifier);
        $session->set(self::ISSUED_AT_SESSION_KEY, time());

        $redirectUri = $this->urlGenerator->generate('app_oidc_google_callback', [
            'locale' => $request->getLocale(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $authorizationUrl = $this->googleOidcClient->buildAuthorizationUrl(
                $redirectUri,
                $state,
                $nonce,
                $codeChallenge,
            );
        } catch (GoogleOidcProviderException) {
            return $this->failedStart($request, 'security.oidc.flash.provider_unavailable', 'security.auth.oidc.google.start_failed');
        }

        $this->authEventLogger->info('security.auth.oidc.google.start', null, $request->getClientIp(), [
            'stateTtl' => self::STATE_TTL_SECONDS,
        ]);

        return new RedirectResponse($authorizationUrl);
    }

    #[Route('/auth/google/callback', name: 'app_oidc_google_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        if (!$this->googleOidcConfig->isEnabled()) {
            return $this->failedCallback($request, 'security.oidc.flash.provider_disabled', 'security.auth.oidc.google.callback_disabled');
        }

        if (!$request->hasSession()) {
            return $this->failedCallback($request, 'security.oidc.flash.callback_failed', 'security.auth.oidc.google.session_missing');
        }

        $session = $request->getSession();
        $state = $request->query->getString('state', '');
        $code = $request->query->getString('code', '');
        $storedState = $session->get(self::STATE_SESSION_KEY);
        $storedCodeVerifier = $session->get(self::CODE_VERIFIER_SESSION_KEY);
        $storedIssuedAt = $session->get(self::ISSUED_AT_SESSION_KEY);

        $session->remove(self::STATE_SESSION_KEY);
        $session->remove(self::NONCE_SESSION_KEY);
        $session->remove(self::CODE_VERIFIER_SESSION_KEY);
        $session->remove(self::ISSUED_AT_SESSION_KEY);

        if (!is_string($storedState) || !is_string($storedCodeVerifier) || !is_int($storedIssuedAt)) {
            return $this->failedCallback($request, 'security.oidc.flash.state_invalid', 'security.auth.oidc.google.callback_state_missing');
        }

        if ('' === trim($state) || '' === trim($code) || !hash_equals($storedState, $state)) {
            return $this->failedCallback($request, 'security.oidc.flash.state_invalid', 'security.auth.oidc.google.callback_state_invalid');
        }

        if (time() - $storedIssuedAt > self::STATE_TTL_SECONDS) {
            return $this->failedCallback($request, 'security.oidc.flash.state_invalid', 'security.auth.oidc.google.callback_state_expired');
        }

        $redirectUri = $this->urlGenerator->generate('app_oidc_google_callback', [
            'locale' => $request->getLocale(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $profile = $this->googleOidcClient->fetchUserProfileFromAuthorizationCode($code, $redirectUri, $storedCodeVerifier);
            $result = $this->googleOidcAuthenticateApplicationService->authenticate($profile);
        } catch (GoogleOidcProviderException) {
            return $this->failedCallback($request, 'security.oidc.flash.provider_unavailable', 'security.auth.oidc.google.callback_provider_failed');
        } catch (GoogleOidcAuthenticationException $exception) {
            return $this->failedCallback($request, $exception->flashMessageKey(), 'security.auth.oidc.google.callback_domain_failed');
        }

        $this->security->login($result->user(), 'form_login', 'main');

        $event = match ($result->action()) {
            GoogleOidcAuthenticationAction::IDENTITY_FOUND => 'security.auth.oidc.google.callback_success',
            GoogleOidcAuthenticationAction::AUTO_LINKED => 'security.auth.oidc.google.callback_auto_linked',
            GoogleOidcAuthenticationAction::USER_CREATED => 'security.auth.oidc.google.callback_user_created',
        };

        $this->authEventLogger->info($event, $result->user()->getEmail(), $request->getClientIp(), [
            'provider' => 'google',
            'action' => $result->action()->value,
        ]);

        return $this->identityLocaleRedirector->toRouteWithRequestLocale($request, 'app_home');
    }

    private function failedStart(Request $request, string $flashMessage, string $event): RedirectResponse
    {
        $this->authEventLogger->warning($event, null, $request->getClientIp(), [
            'provider' => 'google',
        ]);

        return $this->identityFlashResponder->warningToLogin($request, $flashMessage);
    }

    private function failedCallback(Request $request, string $flashMessage, string $event): RedirectResponse
    {
        $this->authEventLogger->warning($event, null, $request->getClientIp(), [
            'provider' => 'google',
        ]);

        return $this->identityFlashResponder->warningToLogin($request, $flashMessage);
    }

    private function randomUrlSafe(): string
    {
        return $this->base64Url(random_bytes(32));
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
