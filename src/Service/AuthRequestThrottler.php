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
use Psr\Cache\CacheItemPoolInterface;

final class AuthRequestThrottler
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool,
    ) {
    }

    public function hitAndIsLimited(string $scope, ?string $clientIp, ?string $email, int $maxAttempts, int $windowSeconds): bool
    {
        $normalizedScope = trim($scope);
        if ('' === $normalizedScope) {
            throw new \InvalidArgumentException('Scope must not be empty.');
        }
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be >= 1.');
        }
        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('windowSeconds must be >= 1.');
        }

        $ipPart = trim((string) $clientIp);
        if ('' === $ipPart) {
            $ipPart = 'unknown';
        }
        $emailPart = mb_strtolower(trim((string) $email));

        $key = sprintf('auth_throttle_%s_%s', $normalizedScope, hash('sha256', $ipPart.'|'.$emailPart));
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
        $item->expiresAt((new DateTimeImmutable())->setTimestamp($expiresAt));
        $this->cachePool->save($item);

        return $count > $maxAttempts;
    }
}
