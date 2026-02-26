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

use App\Entity\MinervaRotationEntity;
use App\Repository\MinervaRotationEntityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final class MinervaRotationRegenerationApplicationService
{
    public function __construct(
        private readonly MinervaRotationGenerationApplicationService $generationService,
        private readonly MinervaRotationEntityRepository $rotationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{deleted: int, inserted: int}
     */
    public function regenerate(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->generationService->generate($from, $to);
        $deleted = $this->rotationRepository->deleteOverlappingRange($from, $to);

        foreach ($rows as $row) {
            $this->entityManager->persist((new MinervaRotationEntity())
                ->setLocation($row['location'])
                ->setListCycle($row['listCycle'])
                ->setStartsAt($row['startsAt'])
                ->setEndsAt($row['endsAt']));
        }
        $this->entityManager->flush();

        return [
            'deleted' => $deleted,
            'inserted' => count($rows),
        ];
    }
}
