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

use App\Domain\Support\Contact\ContactMessageStatusEnum;
use App\Entity\ContactMessageEntity;
use App\Repository\ContactMessageEntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/contact-messages')]
final class ContactMessageController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly ContactMessageEntityRepository $contactMessageRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'app_admin_contact_messages', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = $this->sanitizeQuery($request->query->get('q'));
        $status = $this->sanitizeStatus($request->query->get('status'));
        $perPage = $this->sanitizePositiveInt($request->query->get('perPage'), self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = $this->sanitizePositiveInt($request->query->get('page'), 1);

        $result = $this->contactMessageRepository->findPaginated($query, $status, $page, $perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        if ($page !== $this->sanitizePositiveInt($request->query->get('page'), 1)) {
            $result = $this->contactMessageRepository->findPaginated($query, $status, $page, $perPage);
        }

        return $this->render('admin/contact_messages.html.twig', [
            'rows' => $result['rows'],
            'totalRows' => $totalRows,
            'query' => $query,
            'status' => $status instanceof ContactMessageStatusEnum ? $status->value : '',
            'statusOptions' => ContactMessageStatusEnum::cases(),
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{id<\d+>}/status', name: 'app_admin_contact_messages_set_status', methods: ['POST'])]
    public function setStatus(int $id, Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isValidToken($request, 'admin_contact_messages_set_status_'.$id)) {
            $this->addFlash('warning', 'admin_contact.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_contact_messages', ['locale' => $request->getLocale()]);
        }

        $message = $this->contactMessageRepository->find($id);
        if (!$message instanceof ContactMessageEntity) {
            $this->addFlash('warning', 'admin_contact.flash.message_not_found');

            return $this->redirectToRoute('app_admin_contact_messages', ['locale' => $request->getLocale()]);
        }

        $status = $this->sanitizeStatus($request->request->get('status'));
        if (!$status instanceof ContactMessageStatusEnum) {
            $this->addFlash('warning', 'admin_contact.flash.invalid_status');

            return $this->redirectToRoute('app_admin_contact_messages', ['locale' => $request->getLocale()]);
        }

        $message->setStatus($status);
        $this->contactMessageRepository->save($message);
        $this->addFlash('success', 'admin_contact.flash.status_updated');

        return $this->redirectToRoute('app_admin_contact_messages', ['locale' => $request->getLocale()]);
    }

    private function isValidToken(Request $request, string $tokenId): bool
    {
        $token = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }

    private function sanitizeQuery(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function sanitizeStatus(mixed $value): ?ContactMessageStatusEnum
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ('' === $normalized) {
            return null;
        }

        return ContactMessageStatusEnum::tryFrom($normalized);
    }

    private function sanitizePositiveInt(mixed $value, int $default, ?int $max = null): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && '' !== trim($value) && ctype_digit(trim($value))) {
            $number = (int) trim($value);
        } else {
            return $default;
        }

        if ($number < 1) {
            return $default;
        }

        if (null !== $max && $number > $max) {
            return $max;
        }

        return $number;
    }
}
