<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\ResetPassword\ResetPasswordResult;
use App\Identity\UI\Security\ResetPasswordFeedbackMapper;
use PHPUnit\Framework\TestCase;

final class ResetPasswordFeedbackMapperTest extends TestCase
{
    public function testMapReturnsExpectedPayload(): void
    {
        $mapper = new ResetPasswordFeedbackMapper();

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'security.reset.flash.password_too_short',
            'redirectToLogin' => false,
        ], $mapper->map(ResetPasswordResult::PASSWORD_TOO_SHORT));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'security.reset.flash.password_mismatch',
            'redirectToLogin' => false,
        ], $mapper->map(ResetPasswordResult::PASSWORD_MISMATCH));

        self::assertSame([
            'flashType' => 'warning',
            'flashMessage' => 'security.reset.flash.invalid_or_expired',
            'redirectToLogin' => true,
        ], $mapper->map(ResetPasswordResult::INVALID_OR_EXPIRED));

        self::assertSame([
            'flashType' => 'success',
            'flashMessage' => 'security.reset.flash.success',
            'redirectToLogin' => true,
        ], $mapper->map(ResetPasswordResult::SUCCESS));
    }
}
