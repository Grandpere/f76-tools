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

namespace App\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

final class AuthRequestThrottler
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

    public function hitAndIsLimited(string $scope, ?string $clientIp, ?string $email, int $maxAttempts, int $windowSeconds): bool
    {
        $this->hit($scope, $clientIp, $email, $windowSeconds);

        return $this->isLimited($scope, $clientIp, $email, $maxAttempts);
    }

    public function hit(string $scope, ?string $clientIp, ?string $email, int $windowSeconds): void
    {
        $normalizedScope = trim($scope);
        if ('' === $normalizedScope) {
            throw new InvalidArgumentException('Scope must not be empty.');
        }
        if ($windowSeconds < 1) {
            throw new InvalidArgumentException('windowSeconds must be >= 1.');
        }

        $key = $this->buildKey($normalizedScope, $clientIp, $email);
        $now = time();
        $count = 1;
        $expiresAt = $now + $windowSeconds;

        $item = $this->cachePool->getItem($key);
        if ($item->isHit()) {
            $value = $item->get();
            if (is_array($value)
                && array_key_exists('count', $value)
                && array_key_exists('expiresAt', $value)
                && is_int($value['count'])
                && is_int($value['expiresAt'])
                && $value['expiresAt'] > $now
            ) {
                $count = $value['count'] + 1;
                $expiresAt = $value['expiresAt'];
            }
        }

        $item->set([
            'count' => $count,
            'expiresAt' => $expiresAt,
        ]);
        $item->expiresAt(new DateTimeImmutable()->setTimestamp($expiresAt));
        $this->cachePool->save($item);
    }

    public function isLimited(string $scope, ?string $clientIp, ?string $email, int $maxAttempts): bool
    {
        $normalizedScope = trim($scope);
        if ('' === $normalizedScope) {
            throw new InvalidArgumentException('Scope must not be empty.');
        }
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1.');
        }

        $key = $this->buildKey($normalizedScope, $clientIp, $email);
        $item = $this->cachePool->getItem($key);
        if (!$item->isHit()) {
            return false;
        }

        $value = $item->get();
        if (!is_array($value)
            || !array_key_exists('count', $value)
            || !array_key_exists('expiresAt', $value)
            || !is_int($value['count'])
            || !is_int($value['expiresAt'])
            || $value['expiresAt'] <= time()
        ) {
            return false;
        }

        return $value['count'] > $maxAttempts;
    }

    public function clear(string $scope, ?string $clientIp, ?string $email): void
    {
        $normalizedScope = trim($scope);
        if ('' === $normalizedScope) {
            throw new InvalidArgumentException('Scope must not be empty.');
        }

        $this->cachePool->deleteItem($this->buildKey($normalizedScope, $clientIp, $email));
    }

    private function buildKey(string $scope, ?string $clientIp, ?string $email): string
    {
        $ipPart = trim((string) $clientIp);
        if ('' === $ipPart) {
            $ipPart = 'unknown';
        }
        $emailPart = mb_strtolower(trim((string) $email));

        return sprintf('auth_throttle_%s_%s', $scope, hash('sha256', $ipPart.'|'.$emailPart));
    }
}
