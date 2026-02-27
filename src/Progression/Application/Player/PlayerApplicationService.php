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

namespace App\Progression\Application\Player;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class PlayerApplicationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createForUser(UserEntity $user, string $name): PlayerCreateResult
    {
        $player = new PlayerEntity()
            ->setUser($user)
            ->setName($name);

        try {
            $this->entityManager->persist($player);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            return PlayerCreateResult::nameConflict();
        }

        return PlayerCreateResult::success($player);
    }

    public function renameOwned(PlayerEntity $player, string $name): PlayerRenameResult
    {
        $player->setName($name);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            return PlayerRenameResult::NAME_CONFLICT;
        }

        return PlayerRenameResult::RENAMED;
    }

    public function delete(PlayerEntity $player): void
    {
        $this->entityManager->remove($player);
        $this->entityManager->flush();
    }
}
