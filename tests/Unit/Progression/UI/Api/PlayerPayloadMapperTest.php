<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\PlayerEntity;
use App\Progression\UI\Api\PlayerPayloadMapper;
use PHPUnit\Framework\TestCase;

final class PlayerPayloadMapperTest extends TestCase
{
    public function testMapReturnsExpectedPayload(): void
    {
        $player = (new PlayerEntity())->setName('Main');
        $this->setPlayerPublicId($player, '01J5A6B7C8D9E0F1G2H3J4K5L6');
        $this->setPlayerDates(
            $player,
            new \DateTimeImmutable('2026-02-27T10:00:00+00:00'),
            new \DateTimeImmutable('2026-02-27T10:05:00+00:00'),
        );

        $mapper = new PlayerPayloadMapper();
        $payload = $mapper->map($player);

        self::assertSame([
            'id' => '01J5A6B7C8D9E0F1G2H3J4K5L6',
            'name' => 'Main',
            'createdAt' => '2026-02-27T10:00:00+00:00',
            'updatedAt' => '2026-02-27T10:05:00+00:00',
        ], $payload);
    }

    public function testMapListMapsAllPlayers(): void
    {
        $first = (new PlayerEntity())->setName('First');
        $this->setPlayerPublicId($first, '01J5AAAAAAAAAAAAAAAAAAAAAA');
        $this->setPlayerDates(
            $first,
            new \DateTimeImmutable('2026-02-27T10:00:00+00:00'),
            new \DateTimeImmutable('2026-02-27T10:00:00+00:00'),
        );

        $second = (new PlayerEntity())->setName('Second');
        $this->setPlayerPublicId($second, '01J5BBBBBBBBBBBBBBBBBBBBBB');
        $this->setPlayerDates(
            $second,
            new \DateTimeImmutable('2026-02-27T11:00:00+00:00'),
            new \DateTimeImmutable('2026-02-27T11:10:00+00:00'),
        );

        $mapper = new PlayerPayloadMapper();
        $payload = $mapper->mapList([$first, $second]);

        self::assertCount(2, $payload);
        self::assertSame('First', $payload[0]['name']);
        self::assertSame('Second', $payload[1]['name']);
    }

    private function setPlayerPublicId(PlayerEntity $player, string $publicId): void
    {
        $reflection = new \ReflectionProperty(PlayerEntity::class, 'publicId');
        $reflection->setValue($player, $publicId);
    }

    private function setPlayerDates(PlayerEntity $player, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): void
    {
        $createdReflection = new \ReflectionProperty(PlayerEntity::class, 'createdAt');
        $createdReflection->setValue($player, $createdAt);

        $updatedReflection = new \ReflectionProperty(PlayerEntity::class, 'updatedAt');
        $updatedReflection->setValue($player, $updatedAt);
    }
}

