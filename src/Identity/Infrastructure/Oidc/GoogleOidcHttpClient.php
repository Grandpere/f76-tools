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

namespace App\Identity\Infrastructure\Oidc;

use App\Identity\Application\Oidc\GoogleOidcClient;
use App\Identity\Application\Oidc\GoogleOidcConfig;
use App\Identity\Application\Oidc\GoogleOidcProviderException;
use App\Identity\Application\Oidc\GoogleOidcUserProfile;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOidcHttpClient implements GoogleOidcClient
{
    private const ISSUER_HOST = 'accounts.google.com';
    /** @var list<string> */
    private const ALLOWED_ENDPOINT_HOSTS = [
        'accounts.google.com',
        'oauth2.googleapis.com',
        'openidconnect.googleapis.com',
    ];

    /** @var array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string}|null */
    private ?array $cachedDiscovery = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly GoogleOidcConfig $config,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function buildAuthorizationUrl(string $redirectUri, string $state, string $nonce, string $codeChallenge): string
    {
        $discovery = $this->discover();

        $query = http_build_query([
            'client_id' => $this->config->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return $discovery['authorization_endpoint'].'?'.$query;
    }

    public function fetchUserProfileFromAuthorizationCode(string $code, string $redirectUri, string $codeVerifier): GoogleOidcUserProfile
    {
        $discovery = $this->discover();
        $accessToken = $this->exchangeCodeForAccessToken(
            $discovery['token_endpoint'],
            $code,
            $redirectUri,
            $codeVerifier,
        );

        return $this->fetchUserProfile($discovery['userinfo_endpoint'], $accessToken);
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string}
     */
    private function discover(): array
    {
        if (is_array($this->cachedDiscovery)) {
            return $this->cachedDiscovery;
        }

        $issuer = rtrim($this->config->issuer(), '/');
        if ('' === $issuer) {
            throw new GoogleOidcProviderException('OIDC issuer is empty.');
        }
        $this->assertIssuerAllowed($issuer);

        try {
            $response = $this->httpClient->request('GET', $issuer.'/.well-known/openid-configuration', [
                'timeout' => max(1, $this->timeoutSeconds),
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new GoogleOidcProviderException('Unable to load OIDC discovery.', 0, $exception);
        }

        $authorizationEndpoint = $this->extractStringValue($payload, 'authorization_endpoint');
        $tokenEndpoint = $this->extractStringValue($payload, 'token_endpoint');
        $userinfoEndpoint = $this->extractStringValue($payload, 'userinfo_endpoint');

        if ('' === $authorizationEndpoint || '' === $tokenEndpoint || '' === $userinfoEndpoint) {
            throw new GoogleOidcProviderException('OIDC discovery missing required endpoints.');
        }
        $this->assertEndpointAllowed($authorizationEndpoint, 'authorization_endpoint');
        $this->assertEndpointAllowed($tokenEndpoint, 'token_endpoint');
        $this->assertEndpointAllowed($userinfoEndpoint, 'userinfo_endpoint');

        $this->cachedDiscovery = [
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => $tokenEndpoint,
            'userinfo_endpoint' => $userinfoEndpoint,
        ];

        return $this->cachedDiscovery;
    }

    private function exchangeCodeForAccessToken(string $tokenEndpoint, string $code, string $redirectUri, string $codeVerifier): string
    {
        try {
            $response = $this->httpClient->request('POST', $tokenEndpoint, [
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $this->config->clientId(),
                    'client_secret' => $this->config->clientSecret(),
                    'code_verifier' => $codeVerifier,
                ],
                'timeout' => max(1, $this->timeoutSeconds),
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new GoogleOidcProviderException('OIDC token exchange failed.', 0, $exception);
        }

        $accessToken = $this->extractStringValue($payload, 'access_token');
        if ('' === $accessToken) {
            throw new GoogleOidcProviderException('OIDC token payload missing access_token.');
        }

        return $accessToken;
    }

    private function fetchUserProfile(string $userinfoEndpoint, string $accessToken): GoogleOidcUserProfile
    {
        try {
            $response = $this->httpClient->request('GET', $userinfoEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'timeout' => max(1, $this->timeoutSeconds),
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new GoogleOidcProviderException('OIDC userinfo request failed.', 0, $exception);
        }

        $providerUserId = trim($this->extractStringValue($payload, 'sub'));
        $email = mb_strtolower(trim($this->extractStringValue($payload, 'email')));
        $emailVerified = $this->extractBoolValue($payload, 'email_verified');

        if ('' === $providerUserId || '' === $email) {
            throw new GoogleOidcProviderException('OIDC userinfo payload missing required fields.');
        }

        return new GoogleOidcUserProfile($providerUserId, $email, $emailVerified);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractStringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractBoolValue(array $payload, string $key): bool
    {
        $value = $payload[$key] ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes'], true);
        }
        if (is_int($value)) {
            return 1 === $value;
        }

        return false;
    }

    private function assertIssuerAllowed(string $issuer): void
    {
        $parts = parse_url($issuer);
        if (!is_array($parts)) {
            throw new GoogleOidcProviderException('OIDC issuer URL is invalid.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        if ('https' !== $scheme || self::ISSUER_HOST !== $host) {
            throw new GoogleOidcProviderException('OIDC issuer must be https://accounts.google.com.');
        }
    }

    private function assertEndpointAllowed(string $endpoint, string $key): void
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts)) {
            throw new GoogleOidcProviderException(sprintf('OIDC %s URL is invalid.', $key));
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if ('https' !== $scheme) {
            throw new GoogleOidcProviderException(sprintf('OIDC %s must use HTTPS.', $key));
        }

        if (!in_array($host, self::ALLOWED_ENDPOINT_HOSTS, true)) {
            throw new GoogleOidcProviderException(sprintf('OIDC %s host is not allowed.', $key));
        }
    }
}
