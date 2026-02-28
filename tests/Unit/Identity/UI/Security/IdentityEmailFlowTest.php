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

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\UI\Security\IdentityEmailFlow;
use PHPUnit\Framework\TestCase;

final class IdentityEmailFlowTest extends TestCase
{
    public function testFlowRateLimitSettingsAreSpecificPerFlow(): void
    {
        self::assertSame(3, IdentityEmailFlow::REGISTER->maxAttempts());
        self::assertSame(600, IdentityEmailFlow::REGISTER->windowSeconds());

        self::assertSame(3, IdentityEmailFlow::FORGOT_PASSWORD->maxAttempts());
        self::assertSame(900, IdentityEmailFlow::FORGOT_PASSWORD->windowSeconds());

        self::assertSame(3, IdentityEmailFlow::RESEND_VERIFICATION->maxAttempts());
        self::assertSame(1800, IdentityEmailFlow::RESEND_VERIFICATION->windowSeconds());

        self::assertSame(5, IdentityEmailFlow::CONTACT->maxAttempts());
        self::assertSame(300, IdentityEmailFlow::CONTACT->windowSeconds());
    }
}
