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

namespace App\Identity\UI\Security;

use App\Identity\Application\ChangePassword\ChangePasswordResult;

final class ChangePasswordFeedbackMapper
{
    /**
     * @return array{flashType: 'warning'|'success', flashMessage: string}
     */
    public function map(ChangePasswordResult $result): array
    {
        return match ($result) {
            ChangePasswordResult::CURRENT_PASSWORD_INVALID => [
                'flashType' => 'warning',
                'flashMessage' => 'security.change_password.flash.current_password_invalid',
            ],
            ChangePasswordResult::PASSWORD_TOO_SHORT => [
                'flashType' => 'warning',
                'flashMessage' => 'security.change_password.flash.password_too_short',
            ],
            ChangePasswordResult::PASSWORD_MISMATCH => [
                'flashType' => 'warning',
                'flashMessage' => 'security.change_password.flash.password_mismatch',
            ],
            ChangePasswordResult::SUCCESS => [
                'flashType' => 'success',
                'flashMessage' => 'security.change_password.flash.success',
            ],
        };
    }
}
