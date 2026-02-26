<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support\UI\Admin;

use App\Support\Application\AdminUser\ToggleUserActiveResult;
use App\Support\UI\Admin\ToggleUserActiveFeedbackMapper;
use PHPUnit\Framework\TestCase;

final class ToggleUserActiveFeedbackMapperTest extends TestCase
{
    public function testMapReturnsExpectedFeedbackForEachResult(): void
    {
        $mapper = new ToggleUserActiveFeedbackMapper();

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.user_not_found',
        ], $mapper->map(ToggleUserActiveResult::ACTOR_REQUIRED));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.user_not_found',
        ], $mapper->map(ToggleUserActiveResult::USER_NOT_FOUND));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'admin_users.flash.cannot_change_self_active',
        ], $mapper->map(ToggleUserActiveResult::CANNOT_CHANGE_SELF));

        self::assertSame([
            'flashType' => 'success',
            'flashMessage' => 'admin_users.flash.active_updated',
        ], $mapper->map(ToggleUserActiveResult::UPDATED));
    }
}

