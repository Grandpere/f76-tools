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

use App\Identity\Application\Security\UnlinkOwnGoogleIdentityResult;

final class UnlinkOwnGoogleIdentityFeedbackMapper
{
    /**
     * @return array{flashType: 'success'|'warning', flashMessage: string}
     */
    public function map(UnlinkOwnGoogleIdentityResult $result): array
    {
        return match ($result) {
            UnlinkOwnGoogleIdentityResult::GOOGLE_IDENTITY_NOT_FOUND => [
                'flashType' => 'warning',
                'flashMessage' => 'security.account.flash.google_identity_not_found',
            ],
            UnlinkOwnGoogleIdentityResult::LOCAL_PASSWORD_REQUIRED => [
                'flashType' => 'warning',
                'flashMessage' => 'security.account.flash.google_identity_local_password_required',
            ],
            UnlinkOwnGoogleIdentityResult::UNLINKED => [
                'flashType' => 'success',
                'flashMessage' => 'security.account.flash.google_identity_unlinked',
            ],
        };
    }
}
