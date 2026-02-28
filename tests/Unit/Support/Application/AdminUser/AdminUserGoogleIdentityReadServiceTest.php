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

namespace App\Tests\Unit\Support\Application\AdminUser;

use App\Identity\Application\Oidc\GoogleOidcIdentityReadRepository;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use App\Support\Application\AdminUser\AdminUserGoogleIdentityReadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminUserGoogleIdentityReadServiceTest extends TestCase
{
    private GoogleOidcIdentityReadRepository&MockObject $identityReadRepository;

    protected function setUp(): void
    {
        $this->identityReadRepository = $this->createMock(GoogleOidcIdentityReadRepository::class);
    }

    public function testReturnsIdentityMapIndexedByUserId(): void
    {
        $userOne = new UserEntity();
        $this->forceUserId($userOne, 11);
        $userTwo = new UserEntity();
        $this->forceUserId($userTwo, 22);

        $identity = new UserIdentityEntity()
            ->setUser($userOne)
            ->setProvider('google')
            ->setProviderUserId('sub-11');

        $this->identityReadRepository->expects(self::once())
            ->method('findGoogleIdentitiesByUserIds')
            ->with([11, 22])
            ->willReturn([11 => $identity]);

        $result = $this->service()->getGoogleIdentityByUserId([$userOne, $userTwo]);

        self::assertArrayHasKey(11, $result);
        self::assertSame($identity, $result[11]);
        self::assertArrayNotHasKey(22, $result);
    }

    /**
     * @param int<1, max> $id
     */
    private function forceUserId(UserEntity $user, int $id): void
    {
        $reflection = new ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, $id);
    }

    private function service(): AdminUserGoogleIdentityReadService
    {
        return new AdminUserGoogleIdentityReadService($this->identityReadRepository);
    }
}
