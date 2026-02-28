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

namespace App\Identity\UI\Security\Controller;

use App\Identity\Application\Guard\IdentityCaptchaSiteKeyProvider;
use Symfony\Component\HttpFoundation\Response;

trait IdentityCaptchaRenderControllerTrait
{
    protected function renderWithCaptchaSiteKey(string $template): Response
    {
        return $this->render($template, [
            'captchaSiteKey' => $this->captchaSiteKeyProvider()->getSiteKey(),
        ]);
    }

    abstract protected function captchaSiteKeyProvider(): IdentityCaptchaSiteKeyProvider;
}
