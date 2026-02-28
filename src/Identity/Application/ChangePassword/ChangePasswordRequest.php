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

namespace App\Identity\Application\ChangePassword;

final readonly class ChangePasswordRequest
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
        public string $newPasswordConfirm,
    ) {
    }

    public static function fromRaw(
        string $currentPassword,
        string $newPassword,
        string $newPasswordConfirm,
    ): self {
        return new self(
            trim($currentPassword),
            trim($newPassword),
            trim($newPasswordConfirm),
        );
    }
}
