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
use App\Support\Application\Audit\AuditLogListApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit-logs')]
final class AuditLogController extends AbstractController
{
    private const EXPORT_MAX_ROWS = 10000;

    public function __construct(
        private readonly AdminAuditLogEntityRepository $auditLogRepository,
        private readonly AuditLogListApplicationService $auditLogListApplicationService,
    ) {
    }

    #[Route('', name: 'app_admin_audit_logs', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $listResult = $this->auditLogListApplicationService->list(
            $request->query->get('q'),
            $request->query->get('action'),
            $request->query->get('page'),
            $request->query->get('perPage'),
        );

        return $this->render('admin/audit_logs.html.twig', [
            'rows' => $listResult->rows,
            'totalRows' => $listResult->totalRows,
            'query' => $listResult->query,
            'action' => $listResult->action,
            'actions' => $listResult->actions,
            'page' => $listResult->page,
            'perPage' => $listResult->perPage,
            'totalPages' => $listResult->totalPages,
        ]);
    }

    #[Route('/export.csv', name: 'app_admin_audit_logs_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $query = $this->sanitizeString($request->query->get('q'));
        $action = $this->sanitizeString($request->query->get('action'));
        $rows = $this->auditLogRepository->findForExport($query, $action, self::EXPORT_MAX_ROWS);

        $output = fopen('php://temp', 'wb+');
        if (false === $output) {
            throw new \RuntimeException('Unable to open temporary stream for CSV export.');
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

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        if (false === $csv) {
            throw new \RuntimeException('Unable to build CSV payload.');
        }

        $response = new Response($csv);

        $filename = sprintf('admin_audit_logs_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    private function sanitizeString(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }
}
