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

use App\Entity\UserEntity;
use App\Support\Application\AdminUser\GenerateResetLinkResult;
use App\Support\UI\Admin\GenerateResetLinkFeedbackMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GenerateResetLinkFeedbackMapperTest extends TestCase
{
    public function testMapReturnsExpectedFeedbackForEachResultStatus(): void
    {
        $mapper = new GenerateResetLinkFeedbackMapper();
        $target = new UserEntity()
            ->setEmail('managed@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.user_not_found',
            'flashParams' => [],
            'auditAction' => null,
            'auditContext' => null,
        ], $mapper->map(GenerateResetLinkResult::userNotFound()));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.reset_link_global_rate_limited',
            'flashParams' => [
                '%seconds%' => '60',
                '%count%' => '10',
            ],
            'auditAction' => 'user_generate_reset_link_global_rate_limited',
            'auditContext' => [
                'windowSeconds' => 60,
                'maxRequests' => 10,
            ],
        ], $mapper->map(GenerateResetLinkResult::globalRateLimited($target, 60, 10)));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.reset_link_rate_limited',
            'flashParams' => [
                '%seconds%' => '15',
            ],
            'auditAction' => 'user_generate_reset_link_rate_limited',
            'auditContext' => [
                'remainingSeconds' => 15,
            ],
        ], $mapper->map(GenerateResetLinkResult::cooldownRateLimited($target, 15)));

        self::assertSame([
            'flashType' => 'success',
            'flashMessage' => 'admin_users.flash.reset_link_generated',
            'flashParams' => [],
            'auditAction' => 'user_generate_reset_link',
            'auditContext' => [
                'expiresAt' => '2026-02-27T12:00:00+00:00',
            ],
        ], $mapper->map(GenerateResetLinkResult::generated(
            $target,
            'abc-token',
            new DateTimeImmutable('2026-02-27T12:00:00+00:00'),
        )));
    }
}
