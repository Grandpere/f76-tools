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

namespace App\Catalog\Application\NukeCode;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Throwable;

final class NukeCodeReadApplicationService
{
    private const CACHE_KEY_CURRENT = 'nuke_codes.current.v1';
    private const CACHE_KEY_STALE = 'nuke_codes.stale.v1';

    public function __construct(
        private readonly NukeCodeReadRepository $readRepository,
        private readonly NukeCodeResetCalculator $resetCalculator,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $refreshLeadSeconds = 300,
        private readonly int $staleTtlSeconds = 1800,
    ) {
    }

    public function getCurrent(): NukeCodeSnapshot
    {
        $currentItem = $this->cache->getItem(self::CACHE_KEY_CURRENT);
        if ($currentItem->isHit()) {
            $cachedSnapshot = $this->snapshotFromCachePayload($currentItem->get());
            if ($cachedSnapshot instanceof NukeCodeSnapshot) {
                return $cachedSnapshot;
            }
        }

        try {
            $freshSnapshot = $this->refresh();
        } catch (Throwable $exception) {
            $staleItem = $this->cache->getItem(self::CACHE_KEY_STALE);
            if ($staleItem->isHit()) {
                $staleSnapshot = $this->snapshotFromCachePayload($staleItem->get());
                if ($staleSnapshot instanceof NukeCodeSnapshot) {
                    return $staleSnapshot->asStale();
                }
            }

            throw new RuntimeException('Unable to fetch current nuke codes and no stale cache is available.', 0, $exception);
        }

        return $freshSnapshot;
    }

    private function refresh(): NukeCodeSnapshot
    {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $this->resetCalculator->nextResetUtc($nowUtc);
        $codes = $this->readRepository->fetchCurrent();

        $snapshot = new NukeCodeSnapshot(
            $codes['alpha'],
            $codes['bravo'],
            $codes['charlie'],
            $expiresAt,
            $nowUtc,
        );

        $ttl = $expiresAt->getTimestamp() - $nowUtc->getTimestamp() - max(0, $this->refreshLeadSeconds);
        if ($ttl < 1) {
            $ttl = 1;
        }

        $currentItem = $this->cache->getItem(self::CACHE_KEY_CURRENT);
        $currentItem->set($snapshot->toArray());
        $currentItem->expiresAfter($ttl);
        $this->cache->save($currentItem);

        $staleItem = $this->cache->getItem(self::CACHE_KEY_STALE);
        $staleItem->set($snapshot->toArray());
        $staleItem->expiresAfter(max(60, $this->staleTtlSeconds));
        $this->cache->save($staleItem);

        return $snapshot;
    }

    private function snapshotFromCachePayload(mixed $payload): ?NukeCodeSnapshot
    {
        if (!$this->isSnapshotPayload($payload)) {
            return null;
        }

        return NukeCodeSnapshot::fromArray($payload);
    }

    /**
     * @phpstan-assert-if-true array{alpha: string, bravo: string, charlie: string, expiresAt: string, fetchedAt: string, stale: bool} $payload
     */
    private function isSnapshotPayload(mixed $payload): bool
    {
        if (!is_array($payload)) {
            return false;
        }

        return isset($payload['alpha'], $payload['bravo'], $payload['charlie'], $payload['expiresAt'], $payload['fetchedAt'], $payload['stale'])
            && is_string($payload['alpha'])
            && is_string($payload['bravo'])
            && is_string($payload['charlie'])
            && is_string($payload['expiresAt'])
            && is_string($payload['fetchedAt'])
            && is_bool($payload['stale']);
    }
}
