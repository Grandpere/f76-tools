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

use App\Entity\UserEntity;
use App\Progression\UI\Api\ProgressionApiUserContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProgressionApiUserContextTest extends TestCase
{
    public function testRequireAuthenticatedUserReturnsUserEntity(): void
    {
        $user = new UserEntity()
            ->setEmail('user@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_USER']);

        $context = new ProgressionApiUserContext();
        $result = $context->requireAuthenticatedUser($user);

        self::assertSame($user, $result);
    }

    public function testRequireAuthenticatedUserThrowsForInvalidUser(): void
    {
        $context = new ProgressionApiUserContext();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('User must be authenticated.');

        $context->requireAuthenticatedUser(null);
    }
}
