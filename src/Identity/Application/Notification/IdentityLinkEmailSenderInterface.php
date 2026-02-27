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

namespace App\Identity\Application\Notification;

interface IdentityLinkEmailSenderInterface
{
    public function sendVerificationLink(string $email, string $locale, string $token): void;

    public function sendResetPasswordLink(string $email, string $locale, string $token): void;
}
