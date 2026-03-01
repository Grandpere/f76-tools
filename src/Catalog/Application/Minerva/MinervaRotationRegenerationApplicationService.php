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

use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class MinervaRotationRegenerationApplicationService implements MinervaRotationRegenerator
{
    public function __construct(
        private readonly MinervaRotationGenerator $generationService,
        private readonly MinervaRotationRegenerationRepository $rotationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{deleted: int, inserted: int, skipped: int}
     */
    public function regenerate(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->generationService->generate($from, $to);
        $manualRows = $this->rotationRepository->findManualOverlappingRange($from, $to);
        $deleted = $this->rotationRepository->deleteOverlappingGeneratedRange($from, $to);
        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if ($this->hasManualOverlap($row['startsAt'], $row['endsAt'], $manualRows)) {
                ++$skipped;
                continue;
            }

            $this->entityManager->persist(new MinervaRotationEntity()
                ->setLocation($row['location'])
                ->setListCycle($row['listCycle'])
                ->setStartsAt($row['startsAt'])
                ->setEndsAt($row['endsAt'])
                ->setSource(MinervaRotationSourceEnum::GENERATED));
            ++$inserted;
        }
        $this->entityManager->flush();

        return [
            'deleted' => $deleted,
            'inserted' => $inserted,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param list<MinervaRotationEntity> $manualRows
     */
    private function hasManualOverlap(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, array $manualRows): bool
    {
        foreach ($manualRows as $manualRow) {
            // Use [start, end) semantics: touching boundaries are not overlaps.
            if ($manualRow->getEndsAt() <= $startsAt) {
                continue;
            }
            if ($manualRow->getStartsAt() >= $endsAt) {
                continue;
            }

            return true;
        }

        return false;
    }
}
