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

use App\Identity\Application\Guard\IdentityCaptchaSiteKeyProviderInterface;
use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Support\Application\Contact\ContactSubmissionApplicationService;
use App\Support\Application\Contact\ContactSubmissionInput;
use App\Support\Application\Contact\ContactSubmissionStatus;
use App\Support\UI\Contact\ContactSubmissionResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly IdentityEmailFlowGuard $identityEmailFlowGuard,
        private readonly IdentityCaptchaSiteKeyProviderInterface $captchaSiteKeyProvider,
        private readonly ContactSubmissionApplicationService $contactSubmissionApplicationService,
        private readonly ContactSubmissionResponder $contactSubmissionResponder,
    ) {
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $flow = IdentityEmailFlow::CONTACT;
            $guardResult = $this->identityEmailFlowGuard->guard($request, $flow);
            if (null !== $guardResult->failureFlashMessage) {
                return $this->contactSubmissionResponder->guardFailed($request, $flow, $guardResult->failureFlashMessage);
            }
            $submissionInput = ContactSubmissionInput::create(
                $guardResult->payload->email,
                (string) $request->request->get('subject', ''),
                (string) $request->request->get('message', ''),
            );

            if (!$submissionInput->isValid()) {
                return $this->contactSubmissionResponder->invalidPayload($request, $flow, $submissionInput->email);
            }

            $submissionStatus = $this->contactSubmissionApplicationService->submit(
                $submissionInput,
                $request->getClientIp(),
            );
            if (ContactSubmissionStatus::PERSISTENCE_FAILED === $submissionStatus) {
                return $this->contactSubmissionResponder->persistenceFailed($request, $flow, $submissionInput->email);
            }

            return $this->contactSubmissionResponder->submitted($request, $flow, $submissionInput->email, $submissionStatus);
        }

        return $this->render('security/contact.html.twig', [
            'captchaSiteKey' => $this->captchaSiteKeyProvider->getSiteKey(),
        ]);
    }
}
