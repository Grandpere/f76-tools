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

use App\Support\Domain\Entity\ContactMessageEntity;

interface ContactMessageStatusWriteRepositoryInterface
{
    public function getById(int $id): ?ContactMessageEntity;

    public function save(ContactMessageEntity $message): void;
}
