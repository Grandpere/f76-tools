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

use App\Support\Application\Audit\AuditLogExportApplicationService;
use App\Support\Application\Audit\AuditLogExportQuery;
use App\Support\Application\Audit\AuditLogListApplicationService;
use App\Support\Application\Audit\AuditLogListQuery;
use App\Support\UI\Admin\AuditLogCsvExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit-logs')]
final class AuditLogController extends AbstractController
{
    use AdminRoleGuardControllerTrait;

    public function __construct(
        private readonly AuditLogListApplicationService $auditLogListApplicationService,
        private readonly AuditLogExportApplicationService $auditLogExportApplicationService,
        private readonly AuditLogCsvExporter $auditLogCsvExporter,
    ) {
    }

    #[Route('', name: 'app_admin_audit_logs', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $this->ensureAdminAccess();

        $listResult = $this->auditLogListApplicationService->list(AuditLogListQuery::fromRaw(
            $this->optionalString($request->query->get('q')),
            $this->optionalString($request->query->get('action')),
            $this->optionalIntOrString($request->query->get('page')),
            $this->optionalIntOrString($request->query->get('perPage')),
        ));

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
        $this->ensureAdminAccess();

        $exportResult = $this->auditLogExportApplicationService->export(AuditLogExportQuery::fromRaw(
            $this->optionalString($request->query->get('q')),
            $this->optionalString($request->query->get('action')),
        ));

        return $this->auditLogCsvExporter->buildResponse($exportResult->rows);
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function optionalIntOrString(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }
}
