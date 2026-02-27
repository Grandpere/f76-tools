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

use App\Support\Application\AdminUser\ToggleUserAdminResult;
use App\Support\UI\Admin\ToggleUserAdminFeedbackMapper;
use PHPUnit\Framework\TestCase;

final class ToggleUserAdminFeedbackMapperTest extends TestCase
{
    public function testMapReturnsExpectedFeedbackForEachResult(): void
    {
        $mapper = new ToggleUserAdminFeedbackMapper();

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.user_not_found',
        ], $mapper->map(ToggleUserAdminResult::ACTOR_REQUIRED));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.user_not_found',
        ], $mapper->map(ToggleUserAdminResult::USER_NOT_FOUND));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.cannot_change_self_role',
        ], $mapper->map(ToggleUserAdminResult::CANNOT_CHANGE_SELF));

        self::assertSame([
            'flashType' => 'success',
            'flashMessage' => 'admin_users.flash.role_updated',
        ], $mapper->map(ToggleUserAdminResult::UPDATED));
    }
}
