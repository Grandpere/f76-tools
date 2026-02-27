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

use App\Identity\Application\Security\SignedUrlGenerator;
use Symfony\Component\HttpFoundation\Request;

final class IdentitySignedTokenFailureResolver
{
    public function __construct(
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    /**
     * @param callable(): bool $tokenValidator
     */
    public function resolve(Request $request, callable $tokenValidator, string $invalidFlashMessage): ?string
    {
        if (!$this->signedUrlGenerator->isRequestSignatureValid($request)) {
            return $invalidFlashMessage;
        }

        if (!$tokenValidator()) {
            return $invalidFlashMessage;
        }

        return null;
    }
}
