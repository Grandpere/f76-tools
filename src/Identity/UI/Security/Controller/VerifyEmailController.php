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

use App\Identity\Application\Time\IdentityClockInterface;
use App\Identity\Application\VerifyEmail\VerifyEmailApplicationService;
use App\Identity\UI\Security\IdentityFlashResponder;
use App\Identity\UI\Security\IdentitySignedTokenFailureResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController extends AbstractController
{
    use IdentitySignedTokenValidationControllerTrait;

    public function __construct(
        private readonly VerifyEmailApplicationService $verifyEmailApplicationService,
        private readonly IdentityClockInterface $identityClock,
        private readonly IdentityFlashResponder $identityFlashResponder,
        private readonly IdentitySignedTokenFailureResolver $identitySignedTokenFailureResolver,
    ) {
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function __invoke(string $token, Request $request): RedirectResponse
    {
        $validationFailureFlashMessage = $this->resolveSignedTokenFailureFlashMessage(
            $request,
            fn (): bool => $this->verifyEmailApplicationService->verifyByPlainToken($token, $this->identityClock->now()),
            'security.verify.flash.invalid_or_expired',
        );
        if (null !== $validationFailureFlashMessage) {
            return $this->identityFlashResponder->warningToLogin($request, $validationFailureFlashMessage);
        }

        return $this->identityFlashResponder->successToLogin($request, 'security.verify.flash.success');
    }

    protected function identitySignedTokenFailureResolver(): IdentitySignedTokenFailureResolver
    {
        return $this->identitySignedTokenFailureResolver;
    }
}
