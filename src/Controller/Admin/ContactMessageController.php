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

namespace App\Controller\Admin;

use App\Support\Application\Contact\ContactMessageListApplicationService;
use App\Support\Application\Contact\ContactMessageListQuery;
use App\Support\Application\Contact\ContactMessageStatusUpdateApplicationService;
use App\Support\Application\Contact\ContactMessageStatusUpdateRequest;
use App\Support\Domain\Contact\ContactMessageStatusEnum;
use App\Support\UI\Admin\ContactMessageStatusUpdateResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/contact-messages')]
final class ContactMessageController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;
    use AdminInputSanitizerTrait;

    public function __construct(
        private readonly ContactMessageListApplicationService $contactMessageListApplicationService,
        private readonly ContactMessageStatusUpdateApplicationService $contactMessageStatusUpdateApplicationService,
        private readonly ContactMessageStatusUpdateResponder $contactMessageStatusUpdateResponder,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'app_admin_contact_messages', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureAdminAccess();

        $listResult = $this->contactMessageListApplicationService->list(ContactMessageListQuery::fromRaw(
            $this->optionalString($request->query->get('q')),
            $this->optionalString($request->query->get('status')),
            $this->optionalIntOrString($request->query->get('page')),
            $this->optionalIntOrString($request->query->get('perPage')),
        ));

        return $this->render('admin/contact_messages.html.twig', [
            'rows' => $listResult->rows,
            'totalRows' => $listResult->totalRows,
            'query' => $listResult->query,
            'status' => $listResult->status instanceof ContactMessageStatusEnum ? $listResult->status->value : '',
            'statusOptions' => ContactMessageStatusEnum::cases(),
            'page' => $listResult->page,
            'perPage' => $listResult->perPage,
            'totalPages' => $listResult->totalPages,
        ]);
    }

    #[Route('/{id<\d+>}/status', name: 'app_admin_contact_messages_set_status', methods: ['POST'])]
    public function setStatus(int $id, Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();

        if (!$this->isValidToken($request, 'admin_contact_messages_set_status_'.$id)) {
            return $this->contactMessageStatusUpdateResponder->invalidCsrf($request);
        }

        $result = $this->contactMessageStatusUpdateApplicationService->update(
            $id,
            ContactMessageStatusUpdateRequest::fromRaw($this->optionalString($request->request->get('status'))),
        );

        return $this->contactMessageStatusUpdateResponder->fromResult($request, $result);
    }

    protected function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager;
    }
}
