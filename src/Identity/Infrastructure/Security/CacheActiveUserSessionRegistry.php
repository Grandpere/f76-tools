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

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\Security\ActiveUserSession;
use App\Identity\Application\Security\ActiveUserSessionRegistry;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;

final class CacheActiveUserSessionRegistry implements ActiveUserSessionRegistry
{
    private const TTL_SECONDS = 2592000; // 30 days
    private const MAX_SESSIONS = 20;

    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

    public function registerOrTouch(int $userId, string $sessionId, ?string $ipAddress, ?string $userAgent, DateTimeImmutable $now): void
    {
        $normalizedSessionId = trim($sessionId);
        if ('' === $normalizedSessionId) {
            return;
        }

        $sessions = $this->load($userId);
        $nowTimestamp = $now->getTimestamp();
        $existing = $sessions[$normalizedSessionId] ?? null;

        $sessions[$normalizedSessionId] = [
            'createdAt' => is_array($existing) ? $existing['createdAt'] : $nowTimestamp,
            'lastSeenAt' => $nowTimestamp,
            'ipAddress' => $this->normalizeString($ipAddress),
            'userAgent' => $this->normalizeUserAgent($userAgent),
        ];

        uasort($sessions, static function (array $left, array $right): int {
            $leftTimestamp = $left['lastSeenAt'];
            $rightTimestamp = $right['lastSeenAt'];

            return $rightTimestamp <=> $leftTimestamp;
        });

        if (count($sessions) > self::MAX_SESSIONS) {
            $sessions = array_slice($sessions, 0, self::MAX_SESSIONS, true);
        }

        $this->save($userId, $sessions, $now);
    }

    public function hasAnySession(int $userId): bool
    {
        return [] !== $this->load($userId);
    }

    public function hasSession(int $userId, string $sessionId): bool
    {
        $normalizedSessionId = trim($sessionId);
        if ('' === $normalizedSessionId) {
            return false;
        }

        $sessions = $this->load($userId);

        return array_key_exists($normalizedSessionId, $sessions);
    }

    public function listSessions(int $userId): array
    {
        $sessions = $this->load($userId);
        $rows = [];

        foreach ($sessions as $sessionId => $session) {
            $rows[] = new ActiveUserSession(
                sessionId: $sessionId,
                createdAt: new DateTimeImmutable()->setTimestamp($session['createdAt']),
                lastSeenAt: new DateTimeImmutable()->setTimestamp($session['lastSeenAt']),
                ipAddress: is_string($session['ipAddress'] ?? null) ? $session['ipAddress'] : null,
                userAgent: is_string($session['userAgent'] ?? null) ? $session['userAgent'] : null,
            );
        }

        usort($rows, static fn (ActiveUserSession $left, ActiveUserSession $right): int => $right->lastSeenAt <=> $left->lastSeenAt);

        return $rows;
    }

    public function revokeOtherSessions(int $userId, string $keepSessionId): int
    {
        $normalizedSessionId = trim($keepSessionId);
        $sessions = $this->load($userId);
        if ([] === $sessions) {
            return 0;
        }

        $initialCount = count($sessions);
        $filtered = [];
        if ('' !== $normalizedSessionId && isset($sessions[$normalizedSessionId])) {
            $filtered[$normalizedSessionId] = $sessions[$normalizedSessionId];
        }

        $this->save($userId, $filtered, new DateTimeImmutable());

        return max(0, $initialCount - count($filtered));
    }

    public function revokeSession(int $userId, string $sessionId): void
    {
        $normalizedSessionId = trim($sessionId);
        if ('' === $normalizedSessionId) {
            return;
        }

        $sessions = $this->load($userId);
        if ([] === $sessions || !array_key_exists($normalizedSessionId, $sessions)) {
            return;
        }

        unset($sessions[$normalizedSessionId]);
        $this->save($userId, $sessions, new DateTimeImmutable());
    }

    /**
     * @return array<string, array{createdAt: int, lastSeenAt: int, ipAddress: ?string, userAgent: ?string}>
     */
    private function load(int $userId): array
    {
        $item = $this->cachePool->getItem($this->cacheKey($userId));
        if (!$item->isHit()) {
            return [];
        }

        $value = $item->get();
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $sessionId => $session) {
            if (!is_string($sessionId) || !is_array($session)) {
                continue;
            }
            if (!is_int($session['createdAt'] ?? null) || !is_int($session['lastSeenAt'] ?? null)) {
                continue;
            }

            $rows[$sessionId] = [
                'createdAt' => $session['createdAt'],
                'lastSeenAt' => $session['lastSeenAt'],
                'ipAddress' => is_string($session['ipAddress'] ?? null) ? $session['ipAddress'] : null,
                'userAgent' => is_string($session['userAgent'] ?? null) ? $session['userAgent'] : null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, array{createdAt: int, lastSeenAt: int, ipAddress: ?string, userAgent: ?string}> $sessions
     */
    private function save(int $userId, array $sessions, DateTimeImmutable $now): void
    {
        $item = $this->cachePool->getItem($this->cacheKey($userId));
        $item->set($sessions);
        $item->expiresAt($now->modify(sprintf('+%d seconds', self::TTL_SECONDS)));
        $this->cachePool->save($item);
    }

    private function cacheKey(int $userId): string
    {
        return sprintf('identity_active_sessions_%d', $userId);
    }

    private function normalizeString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = trim($value);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeUserAgent(?string $value): ?string
    {
        $normalized = $this->normalizeString($value);
        if (null === $normalized) {
            return null;
        }

        return mb_substr($normalized, 0, 180);
    }
}
