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

namespace App\Identity\Application\Security;

use Psr\Log\LoggerInterface;

final class AuthEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?AuthAuditLogWriter $authAuditLogWriter = null,
    ) {
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function info(string $event, ?string $email, ?string $clientIp, array $context = []): void
    {
        $payload = $this->buildContext($email, $clientIp, $context);
        $this->logger->info($event, $payload);
        $this->authAuditLogWriter?->write('info', $event, $this->normalizeEmail($email), $clientIp, $payload);
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    public function warning(string $event, ?string $email, ?string $clientIp, array $context = []): void
    {
        $payload = $this->buildContext($email, $clientIp, $context);
        $this->logger->warning($event, $payload);
        $this->authAuditLogWriter?->write('warning', $event, $this->normalizeEmail($email), $clientIp, $payload);
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
