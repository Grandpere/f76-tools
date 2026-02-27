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

namespace App\Tests\Unit\Support\UI\Admin;

use App\Entity\UserEntity;
use App\Support\UI\Admin\AdminAuthenticatedUserContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AdminAuthenticatedUserContextTest extends TestCase
{
    public function testRequireAuthenticatedUserReturnsUserEntity(): void
    {
        $user = (new UserEntity())
            ->setEmail('admin@example.com')
            ->setPassword('hash')
            ->setRoles(['ROLE_ADMIN']);

        $context = new AdminAuthenticatedUserContext();
        $resolved = $context->requireAuthenticatedUser($user);

        self::assertSame($user, $resolved);
    }

    public function testRequireAuthenticatedUserThrowsWhenUserIsMissing(): void
    {
        $context = new AdminAuthenticatedUserContext();

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('User must be authenticated.');
        $context->requireAuthenticatedUser(null);
    }
}
