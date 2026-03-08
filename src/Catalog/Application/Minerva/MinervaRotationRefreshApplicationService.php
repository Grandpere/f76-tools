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

namespace App\Catalog\Application\Minerva;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class MinervaRotationRefreshApplicationService implements MinervaRotationRefresher
{
    public function __construct(
        private readonly MinervaRotationGenerator $generationService,
        private readonly MinervaRotationRegenerator $regenerationService,
        private readonly MinervaRotationRegenerationRepository $rotationRepository,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array{
     *     expectedWindows: int,
     *     missingWindows: int,
     *     covered: bool,
     *     performed: bool,
     *     deleted: int,
     *     inserted: int,
     *     skipped: int
     * }
     */
    public function refresh(DateTimeImmutable $from, DateTimeImmutable $to, bool $dryRun = false): array
    {
        if ($to < $from) {
            throw new InvalidArgumentException('Minerva refresh range is invalid.');
        }

        if ($dryRun) {
            $cacheKey = sprintf('minerva.refresh.dryrun.%s.%s', $from->format('U'), $to->format('U'));

            /** @var array{
             *     expectedWindows: int,
             *     missingWindows: int,
             *     covered: bool,
             *     performed: bool,
             *     deleted: int,
             *     inserted: int,
             *     skipped: int
             * } $cachedResult
             */
            $cachedResult = $this->cache->get($cacheKey, function (ItemInterface $item) use ($from, $to): array {
                $item->expiresAfter(60);

                return $this->computeRefreshResult($from, $to, true);
            });

            return $cachedResult;
        }

        return $this->computeRefreshResult($from, $to, false);
    }

    /**
     * @return array{
     *     expectedWindows: int,
     *     missingWindows: int,
     *     covered: bool,
     *     performed: bool,
     *     deleted: int,
     *     inserted: int,
     *     skipped: int
     * }
     */
    private function computeRefreshResult(DateTimeImmutable $from, DateTimeImmutable $to, bool $dryRun): array
    {

        $expectedRows = $this->generationService->generate($from, $to);
        $existingRows = $this->rotationRepository->findOverlappingRange($from, $to);
        $missingWindows = 0;

        foreach ($expectedRows as $expectedRow) {
            if ($this->hasCoverage($expectedRow['startsAt'], $expectedRow['endsAt'], $existingRows)) {
                continue;
            }
            ++$missingWindows;
        }

        $expectedWindows = count($expectedRows);
        if ($dryRun || 0 === $missingWindows) {
            return [
                'expectedWindows' => $expectedWindows,
                'missingWindows' => $missingWindows,
                'covered' => 0 === $missingWindows,
                'performed' => false,
                'deleted' => 0,
                'inserted' => 0,
                'skipped' => 0,
            ];
        }

        $regeneration = $this->regenerationService->regenerate($from, $to);

        return [
            'expectedWindows' => $expectedWindows,
            'missingWindows' => $missingWindows,
            'covered' => false,
            'performed' => true,
            'deleted' => $regeneration['deleted'],
            'inserted' => $regeneration['inserted'],
            'skipped' => $regeneration['skipped'],
        ];
    }

    /**
     * @param list<\App\Catalog\Domain\Entity\MinervaRotationEntity> $existingRows
     */
    private function hasCoverage(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, array $existingRows): bool
    {
        foreach ($existingRows as $existingRow) {
            // [start, end) overlap semantics.
            if ($existingRow->getEndsAt() <= $startsAt) {
                continue;
            }
            if ($existingRow->getStartsAt() >= $endsAt) {
                continue;
            }

            return true;
        }

        return false;
    }
}
