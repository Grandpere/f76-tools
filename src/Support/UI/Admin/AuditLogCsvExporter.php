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

namespace App\Support\UI\Admin;

use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class AuditLogCsvExporter
{
    /**
     * @param list<AdminAuditLogEntity> $rows
     */
    public function buildResponse(array $rows): Response
    {
        $output = fopen('php://temp', 'wb+');
        if (false === $output) {
            throw new RuntimeException('Unable to open temporary stream for CSV export.');
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
            throw new RuntimeException('Unable to build CSV payload.');
        }

        $response = new Response($csv);
        $filename = sprintf('admin_audit_logs_%s.csv', new DateTimeImmutable()->format('Ymd_His'));
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
