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

use App\Repository\AdminAuditLogEntityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit-logs')]
final class AuditLogController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;

    public function __construct(
        private readonly AdminAuditLogEntityRepository $auditLogRepository,
    ) {
    }

    #[Route('', name: 'app_admin_audit_logs', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = $this->sanitizeQuery($request->query->get('q'));
        $action = $this->sanitizeAction($request->query->get('action'));
        $perPage = $this->sanitizePositiveInt($request->query->get('perPage'), self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = $this->sanitizePositiveInt($request->query->get('page'), 1);

        $result = $this->auditLogRepository->findPaginated($query, $action, $page, $perPage);
        $totalRows = $result['total'];
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);

        if ($page !== $this->sanitizePositiveInt($request->query->get('page'), 1)) {
            $result = $this->auditLogRepository->findPaginated($query, $action, $page, $perPage);
        }

        return $this->render('admin/audit_logs.html.twig', [
            'rows' => $result['rows'],
            'totalRows' => $totalRows,
            'query' => $query,
            'action' => $action,
            'actions' => $this->auditLogRepository->findDistinctActions(),
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ]);
    }

    private function sanitizeQuery(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function sanitizeAction(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
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
