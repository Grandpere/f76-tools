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

namespace App\Support\Domain\Contact;

enum ContactMessageStatusEnum: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case CLOSED = 'closed';
}
