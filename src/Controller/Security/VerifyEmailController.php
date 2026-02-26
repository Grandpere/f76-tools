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

use App\Identity\Application\VerifyEmail\VerifyEmailApplicationService;
use App\Security\SignedUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailApplicationService $verifyEmailApplicationService,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function __invoke(string $token, Request $request): RedirectResponse
    {
        if (!$this->signedUrlGenerator->isRequestSignatureValid($request)) {
            $this->addFlash('warning', 'security.verify.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        if (!$this->verifyEmailApplicationService->verifyByPlainToken($token, new \DateTimeImmutable())) {
            $this->addFlash('warning', 'security.verify.flash.invalid_or_expired');

            return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
        }

        $this->addFlash('success', 'security.verify.flash.success');

        return $this->redirectToRoute('app_login', ['locale' => $request->getLocale()]);
    }
}
