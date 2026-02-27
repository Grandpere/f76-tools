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

use App\Identity\Infrastructure\Guard\TurnstileVerifier;
use Symfony\Component\HttpFoundation\Response;

trait IdentityCaptchaRenderControllerTrait
{
    protected function renderWithCaptchaSiteKey(string $template): Response
    {
        return $this->render($template, [
            'captchaSiteKey' => $this->turnstileVerifier()->getSiteKey(),
        ]);
    }

    abstract protected function turnstileVerifier(): TurnstileVerifier;
}
