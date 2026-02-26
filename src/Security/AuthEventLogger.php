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

namespace App\Security;

use Psr\Log\LoggerInterface;

final class AuthEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function info(string $event, ?string $email, ?string $clientIp, array $context = []): void
    {
        $this->logger->info($event, $this->buildContext($email, $clientIp, $context));
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function warning(string $event, ?string $email, ?string $clientIp, array $context = []): void
    {
        $this->logger->warning($event, $this->buildContext($email, $clientIp, $context));
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     *
     * @return array<string, bool|float|int|string|null>
     */
    private function buildContext(?string $email, ?string $clientIp, array $context): array
    {
        $normalizedEmail = $this->normalizeEmail($email);

        return $context + [
            'clientIp' => $clientIp,
            'emailHash' => null !== $normalizedEmail ? hash('sha256', $normalizedEmail) : null,
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (null === $email) {
            return null;
        }

        $normalized = mb_strtolower(trim($email));

        return '' !== $normalized ? $normalized : null;
    }
}
