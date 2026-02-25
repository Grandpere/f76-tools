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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit-logs')]
final class AuditLogController extends AbstractController
{
    private const DEFAULT_PER_PAGE = 30;
    private const MAX_PER_PAGE = 200;
    private const EXPORT_MAX_ROWS = 10000;

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

    #[Route('/export.csv', name: 'app_admin_audit_logs_export', methods: ['GET'])]
    public function export(Request $request): StreamedResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = $this->sanitizeQuery($request->query->get('q'));
        $action = $this->sanitizeAction($request->query->get('action'));
        $rows = $this->auditLogRepository->findForExport($query, $action, self::EXPORT_MAX_ROWS);

        $response = new StreamedResponse(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            if (false === $output) {
                return;
            }

            fputcsv($output, ['occurred_at', 'action', 'actor_email', 'target_email', 'context_json'], ',', '"', '\\');
            foreach ($rows as $row) {
                $contextJson = '';
                if (is_array($row->getContext())) {
                    $contextJson = (string) json_encode($row->getContext(), JSON_UNESCAPED_SLASHES);
                }

                fputcsv($output, [
                    $row->getOccurredAt()->format('Y-m-d H:i:s'),
                    $row->getAction(),
                    $row->getActorUser()->getEmail(),
                    $row->getTargetUser()?->getEmail() ?? '',
                    $contextJson,
                ], ',', '"', '\\');
            }

            fclose($output);
        });

        $filename = sprintf('admin_audit_logs_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
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
