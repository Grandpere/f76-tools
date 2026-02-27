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

namespace App\Support\Application\Contact;

use App\Domain\Support\Contact\ContactMessageStatusEnum;

final readonly class ContactMessageStatusUpdateRequest
{
    public function __construct(
        public ?ContactMessageStatusEnum $status,
    ) {
    }

    public static function fromRaw(mixed $rawStatus): self
    {
        if (!is_string($rawStatus)) {
            return new self(null);
        }

        $normalized = trim($rawStatus);
        if ('' === $normalized) {
            return new self(null);
        }

        return new self(ContactMessageStatusEnum::tryFrom($normalized));
    }
}
