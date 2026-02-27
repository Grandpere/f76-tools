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

final readonly class IdentityEmailFormPayload
{
    public function __construct(
        public string $email,
        public string $csrfToken,
        public string $honeypotValue,
        public string $captchaToken,
    ) {
    }
}
