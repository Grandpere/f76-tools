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
use InvalidArgumentException;

final class MinervaRotationOverrideApplicationService
{
    public function __construct(
        private readonly MinervaRotationRegenerationRepository $rotationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<MinervaRotationEntity>
     */
    public function listManualOverrides(): array
    {
        return $this->rotationRepository->findManualOrdered();
    }

    public function createManualOverride(
        string $location,
        int $listCycle,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
    ): void {
        if ($endsAt < $startsAt) {
            throw new InvalidArgumentException('Manual Minerva override range is invalid.');
        }

        $this->rotationRepository->deleteOverlappingGeneratedRange($startsAt, $endsAt);

        $this->entityManager->persist(new MinervaRotationEntity()
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt)
            ->setSource(MinervaRotationSourceEnum::MANUAL));
        $this->entityManager->flush();
    }

    public function deleteManualOverride(int $id): bool
    {
        $override = $this->rotationRepository->findManualById($id);
        if (!$override instanceof MinervaRotationEntity) {
            return false;
        }

        $this->entityManager->remove($override);
        $this->entityManager->flush();

        return true;
    }
}
