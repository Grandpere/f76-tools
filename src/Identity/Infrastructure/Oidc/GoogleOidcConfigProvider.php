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

use App\Identity\Application\Oidc\GoogleOidcConfig;

final class GoogleOidcConfigProvider implements GoogleOidcConfig
{
    private const EXPECTED_ISSUER = 'https://accounts.google.com';

    public function __construct(
        private readonly bool $enabled,
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function isEnabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return $this->isIssuerAllowed($this->issuer())
            && '' !== trim($this->clientId)
            && '' !== trim($this->clientSecret);
    }

    public function issuer(): string
    {
        return trim($this->issuer);
    }

    public function clientId(): string
    {
        return trim($this->clientId);
    }

    public function clientSecret(): string
    {
        return trim($this->clientSecret);
    }

    private function isIssuerAllowed(string $issuer): bool
    {
        $normalized = rtrim(trim($issuer), '/');

        return self::EXPECTED_ISSUER === $normalized;
    }
}
