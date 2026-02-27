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

namespace App\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

trait AdminCsrfTokenValidatorTrait
{
    protected function isValidToken(Request $request, string $tokenId): bool
    {
        $token = (string) $request->request->get('_csrf_token', '');

        return $this->csrfTokenManager()->isTokenValid(new CsrfToken($tokenId, $token));
    }

    abstract protected function csrfTokenManager(): CsrfTokenManagerInterface;
}
