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

namespace App\Tests\Unit\Progression\UI\Api;

use App\Entity\PlayerEntity;
use App\Progression\UI\Api\PlayerControllerWriteResponder;
use App\Progression\UI\Api\PlayerPayloadMapper;
use App\Progression\UI\Api\ProgressionApiErrorResponder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\JsonResponse;

final class PlayerControllerWriteResponderTest extends TestCase
{
    public function testInvalidPlayerNameAndNameConflictResponses(): void
    {
        $responder = new PlayerControllerWriteResponder(new PlayerPayloadMapper(), new ProgressionApiErrorResponder());

        $invalid = $responder->invalidPlayerName();
        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $invalid->getStatusCode());
        self::assertSame('{"error":"Invalid player name."}', $invalid->getContent());

        $conflict = $responder->playerNameAlreadyExists();
        self::assertSame(JsonResponse::HTTP_CONFLICT, $conflict->getStatusCode());
        self::assertSame('{"error":"Player name already exists."}', $conflict->getContent());
    }

    public function testCreatedAndUpdatedUsePayloadMapper(): void
    {
        $responder = new PlayerControllerWriteResponder(new PlayerPayloadMapper(), new ProgressionApiErrorResponder());

        $player = new PlayerEntity()->setName('Main');
        $this->setPlayerPublicId($player, '01J5A6B7C8D9E0F1G2H3J4K5L6');
        $this->setPlayerDates(
            $player,
            new DateTimeImmutable('2026-02-27T10:00:00+00:00'),
            new DateTimeImmutable('2026-02-27T10:05:00+00:00'),
        );

        $created = $responder->created($player);
        self::assertSame(JsonResponse::HTTP_CREATED, $created->getStatusCode());
        self::assertSame('{"id":"01J5A6B7C8D9E0F1G2H3J4K5L6","name":"Main","createdAt":"2026-02-27T10:00:00+00:00","updatedAt":"2026-02-27T10:05:00+00:00"}', $created->getContent());

        $updated = $responder->updated($player);
        self::assertSame(JsonResponse::HTTP_OK, $updated->getStatusCode());
        self::assertSame('{"id":"01J5A6B7C8D9E0F1G2H3J4K5L6","name":"Main","createdAt":"2026-02-27T10:00:00+00:00","updatedAt":"2026-02-27T10:05:00+00:00"}', $updated->getContent());
    }

    public function testDeletedReturnsNoContent(): void
    {
        $responder = new PlayerControllerWriteResponder(new PlayerPayloadMapper(), new ProgressionApiErrorResponder());
        $response = $responder->deleted();

        self::assertSame(JsonResponse::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame('', $response->getContent());
    }

    private function setPlayerPublicId(PlayerEntity $player, string $publicId): void
    {
        $reflection = new ReflectionProperty(PlayerEntity::class, 'publicId');
        $reflection->setValue($player, $publicId);
    }

    private function setPlayerDates(PlayerEntity $player, DateTimeImmutable $createdAt, DateTimeImmutable $updatedAt): void
    {
        $createdReflection = new ReflectionProperty(PlayerEntity::class, 'createdAt');
        $createdReflection->setValue($player, $createdAt);

        $updatedReflection = new ReflectionProperty(PlayerEntity::class, 'updatedAt');
        $updatedReflection->setValue($player, $updatedAt);
    }
}
