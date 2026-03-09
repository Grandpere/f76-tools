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

namespace App\Tests\Unit\Identity\Infrastructure\Oidc;

use App\Identity\Infrastructure\Oidc\GoogleOidcConfigProvider;
use PHPUnit\Framework\TestCase;

final class GoogleOidcConfigProviderTest extends TestCase
{
    public function testIsEnabledReturnsFalseWhenFeatureFlagDisabled(): void
    {
        $provider = new GoogleOidcConfigProvider(
            false,
            'https://accounts.google.com',
            'client-id',
            'client-secret',
        );

        self::assertFalse($provider->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenIssuerIsNotAllowed(): void
    {
        $provider = new GoogleOidcConfigProvider(
            true,
            'https://evil.example.com',
            'client-id',
            'client-secret',
        );

        self::assertFalse($provider->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenCredentialsMissing(): void
    {
        $provider = new GoogleOidcConfigProvider(
            true,
            'https://accounts.google.com',
            '',
            'client-secret',
        );

        self::assertFalse($provider->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenIssuerAndCredentialsAreValid(): void
    {
        $provider = new GoogleOidcConfigProvider(
            true,
            'https://accounts.google.com/',
            'client-id',
            'client-secret',
        );

        self::assertTrue($provider->isEnabled());
        self::assertSame('https://accounts.google.com/', $provider->issuer());
        self::assertSame('client-id', $provider->clientId());
        self::assertSame('client-secret', $provider->clientSecret());
    }
}

