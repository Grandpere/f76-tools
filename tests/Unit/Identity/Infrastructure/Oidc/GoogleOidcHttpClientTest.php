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

use App\Identity\Application\Oidc\GoogleOidcConfig;
use App\Identity\Application\Oidc\GoogleOidcProviderException;
use App\Identity\Infrastructure\Oidc\GoogleOidcHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleOidcHttpClientTest extends TestCase
{
    public function testRejectsIssuerOutsideExpectedGoogleHost(): void
    {
        $client = new GoogleOidcHttpClient(
            new MockHttpClient(),
            $this->config('https://evil.example.com'),
        );

        $this->expectException(GoogleOidcProviderException::class);
        $this->expectExceptionMessage('OIDC issuer must be https://accounts.google.com.');

        $client->buildAuthorizationUrl('http://localhost/callback', 'state', 'nonce', 'challenge');
    }

    public function testRejectsDiscoveryEndpointsOutsideAllowlist(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://evil.example.com/token',
                'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new GoogleOidcHttpClient($httpClient, $this->config('https://accounts.google.com'));

        $this->expectException(GoogleOidcProviderException::class);
        $this->expectExceptionMessage('OIDC token_endpoint host is not allowed.');

        $client->buildAuthorizationUrl('http://localhost/callback', 'state', 'nonce', 'challenge');
    }

    public function testFetchUserProfileSucceedsWithAllowedGoogleEndpoints(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'access_token' => 'access-token',
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'sub' => 'sub-123',
                'email' => 'OIDC@EXAMPLE.COM',
                'email_verified' => true,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new GoogleOidcHttpClient($httpClient, $this->config('https://accounts.google.com'));
        $profile = $client->fetchUserProfileFromAuthorizationCode('code', 'http://localhost/callback', 'verifier');

        self::assertSame('sub-123', $profile->providerUserId());
        self::assertSame('oidc@example.com', $profile->email());
        self::assertTrue($profile->emailVerified());
    }

    public function testDiscoveryRequestUsesConfiguredTimeout(): void
    {
        $capturedTimeout = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedTimeout): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringContainsString('/.well-known/openid-configuration', $url);
            $capturedTimeout = $options['timeout'] ?? null;

            return new MockResponse(json_encode([
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            ], JSON_THROW_ON_ERROR));
        });

        $client = new GoogleOidcHttpClient($httpClient, $this->config('https://accounts.google.com'), 7);
        $client->buildAuthorizationUrl('http://localhost/callback', 'state', 'nonce', 'challenge');

        self::assertEquals(7, $capturedTimeout);
    }

    private function config(string $issuer): GoogleOidcConfig
    {
        return new class($issuer) implements GoogleOidcConfig {
            public function __construct(
                private readonly string $issuer,
            ) {
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function issuer(): string
            {
                return $this->issuer;
            }

            public function clientId(): string
            {
                return 'client-id';
            }

            public function clientSecret(): string
            {
                return 'client-secret';
            }
        };
    }
}
