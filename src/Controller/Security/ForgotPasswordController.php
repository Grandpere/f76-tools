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

namespace App\Controller\Security;

use App\Entity\UserEntity;
use App\Repository\UserEntityRepository;
use App\Service\AuthRequestThrottler;
use App\Service\TurnstileVerifier;
use App\Security\AuthEventLogger;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ForgotPasswordController extends AbstractController
{
    private const RESET_LINK_COOLDOWN_SECONDS = 60;
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public function __construct(
        private readonly UserEntityRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
        private readonly AuthRequestThrottler $requestThrottler,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly AuthEventLogger $authEventLogger,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $csrfToken = (string) $request->request->get('_csrf_token', '');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('forgot_password', $csrfToken))) {
                $this->authEventLogger->warning('security.auth.forgot_password.invalid_csrf', null, $request->getClientIp());
                $this->addFlash('warning', 'security.forgot.flash.invalid_csrf');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            if ('' !== trim((string) $request->request->get('website', ''))) {
                $this->authEventLogger->warning('security.auth.forgot_password.honeypot_triggered', null, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            if (!$this->turnstileVerifier->verify((string) $request->request->get('cf-turnstile-response', ''), $request->getClientIp())) {
                $this->authEventLogger->warning('security.auth.forgot_password.captcha_invalid', $email, $request->getClientIp());
                $this->addFlash('warning', 'security.auth.flash.captcha_invalid');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }

            if ($this->requestThrottler->hitAndIsLimited(
                scope: 'forgot_password',
                clientIp: $request->getClientIp(),
                email: $email,
                maxAttempts: self::RATE_LIMIT_MAX_ATTEMPTS,
                windowSeconds: self::RATE_LIMIT_WINDOW_SECONDS,
            )) {
                $this->authEventLogger->warning('security.auth.forgot_password.rate_limited', $email, $request->getClientIp(), [
                    'scope' => 'forgot_password',
                    'maxAttempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                    'windowSeconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                ]);
                $this->addFlash('warning', 'security.auth.flash.rate_limited');

                return $this->redirectToRoute('app_forgot_password', ['locale' => $request->getLocale()]);
            }

            $user = null;
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $found = $this->userRepository->findOneByEmail($email);
                if ($found instanceof UserEntity) {
                    $user = $found;
                }
            }

            if ($user instanceof UserEntity) {
                $now = new DateTimeImmutable();
                $requestedAt = $user->getResetPasswordRequestedAt();
                if (!$requestedAt instanceof DateTimeImmutable || ($now->getTimestamp() - $requestedAt->getTimestamp()) >= self::RESET_LINK_COOLDOWN_SECONDS) {
                    $token = bin2hex(random_bytes(32));
                    $user->setResetPasswordTokenHash(hash('sha256', $token));
                    $user->setResetPasswordExpiresAt($now->add(new DateInterval('PT2H')));
                    $user->setResetPasswordRequestedAt($now);
                    $this->entityManager->flush();

                    $resetUrl = $this->urlGenerator->generate('app_reset_password', [
                        'locale' => $request->getLocale(),
                        'token' => $token,
                    ], UrlGeneratorInterface::ABSOLUTE_URL);
                    try {
                        $this->mailer->send(
                            (new Email())
                                ->from('no-reply@f76.local')
                                ->to($email)
                                ->subject($this->translator->trans('security.forgot.email_subject'))
                                ->text(sprintf("%s\n\n%s", $this->translator->trans('security.forgot.email_intro'), $resetUrl)),
                        );
                    } catch (\Throwable) {
                        // Keep same user-facing response to avoid account enumeration.
                    }

                    $this->authEventLogger->info('security.auth.forgot_password.reset_token_issued', $email, $request->getClientIp());
                }
            }

            $this->addFlash('success', 'security.forgot.flash.request_sent');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'captchaSiteKey' => $this->turnstileVerifier->getSiteKey(),
        ]);
    }
}
