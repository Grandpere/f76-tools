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
use App\Support\Application\Audit\AuditLogListApplicationService;
use App\Support\UI\Admin\AuditLogCsvExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit-logs')]
final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogListApplicationService $auditLogListApplicationService,
        private readonly AuditLogExportApplicationService $auditLogExportApplicationService,
        private readonly AuditLogCsvExporter $auditLogCsvExporter,
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

        $exportResult = $this->auditLogExportApplicationService->export(
            $request->query->get('q'),
            $request->query->get('action'),
        );

        return $this->auditLogCsvExporter->buildResponse($exportResult->rows);
    }
}
