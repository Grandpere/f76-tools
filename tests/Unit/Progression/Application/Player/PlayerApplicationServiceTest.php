<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Application\Player;

use App\Entity\PlayerEntity;
use App\Entity\UserEntity;
use App\Progression\Application\Player\PlayerApplicationService;
use App\Progression\Application\Player\PlayerCreateResult;
use App\Progression\Application\Player\PlayerRenameResult;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PlayerApplicationServiceTest extends TestCase
{
    public function testCreateForUserReturnsSuccessWhenFlushSucceeds(): void
    {
        $user = $this->createUser('user@example.com');

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(PlayerEntity::class));
        $entityManager->expects(self::once())->method('flush');

        $service = new PlayerApplicationService($entityManager);
        $result = $service->createForUser($user, 'Main');

        self::assertTrue($result->isOk());
        self::assertInstanceOf(PlayerEntity::class, $result->getPlayer());
        self::assertSame('Main', $result->getPlayer()->getName());
    }

    public function testCreateForUserReturnsNameConflictOnUniqueViolation(): void
    {
        $user = $this->createUser('user@example.com');

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(PlayerEntity::class));
        $entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createUniqueConstraintViolationException());

        $service = new PlayerApplicationService($entityManager);
        $result = $service->createForUser($user, 'Main');

        self::assertFalse($result->isOk());
        self::assertNull($result->getPlayer());
        self::assertEquals(PlayerCreateResult::nameConflict(), $result);
    }

    public function testRenameOwnedReturnsRenamedWhenFlushSucceeds(): void
    {
        $player = (new PlayerEntity())->setName('Old Name');

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new PlayerApplicationService($entityManager);
        $result = $service->renameOwned($player, 'New Name');

        self::assertSame(PlayerRenameResult::RENAMED, $result);
        self::assertSame('New Name', $player->getName());
    }

    public function testRenameOwnedReturnsNameConflictOnUniqueViolation(): void
    {
        $player = (new PlayerEntity())->setName('Old Name');

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createUniqueConstraintViolationException());

        $service = new PlayerApplicationService($entityManager);
        $result = $service->renameOwned($player, 'New Name');

        self::assertSame(PlayerRenameResult::NAME_CONFLICT, $result);
    }

    private function createUser(string $email): UserEntity
    {
        return (new UserEntity())
            ->setEmail($email)
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);
    }

    private function createUniqueConstraintViolationException(): UniqueConstraintViolationException
    {
        $driverException = new class('duplicate key') extends \RuntimeException implements DriverException {
            public function getSQLState(): string
            {
                return '23505';
            }
        };

        return new UniqueConstraintViolationException($driverException, null);
    }
}
