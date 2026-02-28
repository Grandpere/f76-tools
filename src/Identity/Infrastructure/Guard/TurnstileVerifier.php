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

namespace App\Identity\Infrastructure\Guard;

use App\Identity\Application\Guard\IdentityCaptchaSiteKeyProvider;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TurnstileVerifier implements IdentityCaptchaSiteKeyProvider
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $siteKey,
        private readonly string $secretKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->siteKey) && '' !== trim($this->secretKey);
    }

    public function getSiteKey(): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return $this->siteKey;
    }

    public function verify(?string $responseToken, ?string $clientIp): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $token = trim((string) $responseToken);
        if ('' === $token) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => (string) $clientIp,
                ],
            ]);
            $payload = $response->toArray(false);
        } catch (ExceptionInterface) {
            return false;
        }

        return isset($payload['success']) && true === $payload['success'];
    }
}
