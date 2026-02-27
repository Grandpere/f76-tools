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

namespace App\Progression\UI\Api;

use App\Entity\PlayerEntity;

final class PlayerPayloadMapper
{
    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     createdAt: string,
     *     updatedAt: string
     * }
     */
    public function map(PlayerEntity $player): array
    {
        return [
            'id' => $player->getPublicId(),
            'name' => $player->getName(),
            'createdAt' => $player->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $player->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<PlayerEntity> $players
     *
     * @return list<array{
     *     id: string,
     *     name: string,
     *     createdAt: string,
     *     updatedAt: string
     * }>
     */
    public function mapList(array $players): array
    {
        return array_map(
            fn (PlayerEntity $player): array => $this->map($player),
            $players,
        );
    }
}
