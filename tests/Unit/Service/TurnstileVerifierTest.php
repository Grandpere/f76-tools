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

namespace App\Tests\Unit\Service;

use App\Identity\Infrastructure\Guard\TurnstileVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TurnstileVerifierTest extends TestCase
{
    public function testVerifierIsDisabledWhenKeysAreMissing(): void
    {
        $verifier = new TurnstileVerifier(new MockHttpClient(), '', '');

        self::assertFalse($verifier->isEnabled());
        self::assertNull($verifier->getSiteKey());
        self::assertTrue($verifier->verify(null, '127.0.0.1'));
    }

    public function testVerifierRejectsMissingTokenWhenEnabled(): void
    {
        $verifier = new TurnstileVerifier(new MockHttpClient(), 'site-key', 'secret-key');

        self::assertTrue($verifier->isEnabled());
        self::assertFalse($verifier->verify('', '127.0.0.1'));
    }

    public function testVerifierAcceptsSuccessfulApiResponse(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['success' => true], JSON_THROW_ON_ERROR)),
        ]);
        $verifier = new TurnstileVerifier($client, 'site-key', 'secret-key');

        self::assertTrue($verifier->verify('token-value', '127.0.0.1'));
    }

    public function testVerifierRejectsFailedApiResponse(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['success' => false], JSON_THROW_ON_ERROR)),
        ]);
        $verifier = new TurnstileVerifier($client, 'site-key', 'secret-key');

        self::assertFalse($verifier->verify('token-value', '127.0.0.1'));
    }
}
