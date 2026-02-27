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

namespace App\Tests\Unit\Support\UI\Admin;

use App\Entity\AdminAuditLogEntity;
use App\Entity\UserEntity;
use App\Support\UI\Admin\AuditLogCsvExporter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuditLogCsvExporterTest extends TestCase
{
    public function testBuildResponseContainsHeaderAndAuditRow(): void
    {
        $actor = new UserEntity()->setEmail('admin@example.com');
        $target = new UserEntity()->setEmail('target@example.com');

        $row = new AdminAuditLogEntity()
            ->setActorUser($actor)
            ->setTargetUser($target)
            ->setAction('user_toggle_active')
            ->setContext(['reason' => 'manual'])
            ->setOccurredAt(new DateTimeImmutable('2026-02-26 10:00:00'));

        $exporter = new AuditLogCsvExporter();
        $response = $exporter->buildResponse([$row]);
        $content = $response->getContent() ?: '';

        self::assertStringContainsString('occurred_at,action,actor_email,target_email,context_json', $content);
        self::assertStringContainsString('"2026-02-26 10:00:00",user_toggle_active,admin@example.com,target@example.com', $content);
        self::assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }
}
