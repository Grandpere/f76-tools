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

namespace App\Progression\UI\Api;

use App\Identity\Domain\Entity\UserEntity;

trait ProgressionAuthenticatedUserControllerTrait
{
    protected function getAuthenticatedUser(): UserEntity
    {
        return $this->progressionApiUserContext()->requireAuthenticatedUser($this->getUser());
    }

    abstract protected function progressionApiUserContext(): ProgressionApiUserContext;
}
