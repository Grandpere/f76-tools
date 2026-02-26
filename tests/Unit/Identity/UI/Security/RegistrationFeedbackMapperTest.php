<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\Registration\RegisterUserStatus;
use App\Identity\UI\Security\RegistrationFeedbackMapper;
use PHPUnit\Framework\TestCase;

final class RegistrationFeedbackMapperTest extends TestCase
{
    public function testWarningFlashForEachStatus(): void
    {
        $mapper = new RegistrationFeedbackMapper();

        self::assertSame('security.register.flash.invalid_email', $mapper->warningFlash(RegisterUserStatus::INVALID_EMAIL));
        self::assertSame('security.register.flash.password_too_short', $mapper->warningFlash(RegisterUserStatus::PASSWORD_TOO_SHORT));
        self::assertSame('security.register.flash.password_mismatch', $mapper->warningFlash(RegisterUserStatus::PASSWORD_MISMATCH));
        self::assertSame('security.register.flash.email_exists', $mapper->warningFlash(RegisterUserStatus::EMAIL_EXISTS));
        self::assertNull($mapper->warningFlash(RegisterUserStatus::SUCCESS));
    }
}
