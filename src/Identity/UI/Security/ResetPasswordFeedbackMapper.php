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

use App\Identity\Application\ResetPassword\ResetPasswordResult;

final class ResetPasswordFeedbackMapper
{
    /**
     * @return array{flashType: 'warning'|'success', flashMessage: string, redirectToLogin: bool}
     */
    public function map(ResetPasswordResult $result): array
    {
        return match ($result) {
            ResetPasswordResult::PASSWORD_TOO_SHORT => [
                'flashType' => 'warning',
                'flashMessage' => 'security.reset.flash.password_too_short',
                'redirectToLogin' => false,
            ],
            ResetPasswordResult::PASSWORD_MISMATCH => [
                'flashType' => 'warning',
                'flashMessage' => 'security.reset.flash.password_mismatch',
                'redirectToLogin' => false,
            ],
            ResetPasswordResult::INVALID_OR_EXPIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'security.reset.flash.invalid_or_expired',
                'redirectToLogin' => true,
            ],
            ResetPasswordResult::SUCCESS => [
                'flashType' => 'success',
                'flashMessage' => 'security.reset.flash.success',
                'redirectToLogin' => true,
            ],
        };
    }
}
