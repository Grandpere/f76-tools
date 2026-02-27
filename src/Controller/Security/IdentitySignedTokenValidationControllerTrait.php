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

namespace App\Controller\Security;

use App\Identity\UI\Security\IdentitySignedTokenFailureResolver;
use Symfony\Component\HttpFoundation\Request;

trait IdentitySignedTokenValidationControllerTrait
{
    protected function resolveSignedTokenFailureFlashMessage(Request $request, callable $tokenValidator, string $invalidFlashMessage): ?string
    {
        return $this->identitySignedTokenFailureResolver()->resolve($request, $tokenValidator, $invalidFlashMessage);
    }

    abstract protected function identitySignedTokenFailureResolver(): IdentitySignedTokenFailureResolver;
}
