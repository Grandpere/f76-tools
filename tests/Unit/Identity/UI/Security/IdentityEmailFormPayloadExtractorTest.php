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

use App\Identity\UI\Security\IdentityEmailFormPayloadExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class IdentityEmailFormPayloadExtractorTest extends TestCase
{
    public function testExtractNormalizesExpectedFields(): void
    {
        $extractor = new IdentityEmailFormPayloadExtractor();

        $request = Request::create('/register', 'POST', [
            'email' => '  USER@Example.COM ',
            '_csrf_token' => 'csrf-token',
            'website' => 'honeypot',
            'cf-turnstile-response' => 'captcha-token',
        ]);

        $payload = $extractor->extract($request);

        self::assertSame('user@example.com', $payload->email);
        self::assertSame('csrf-token', $payload->csrfToken);
        self::assertSame('honeypot', $payload->honeypotValue);
        self::assertSame('captcha-token', $payload->captchaToken);
    }
}
