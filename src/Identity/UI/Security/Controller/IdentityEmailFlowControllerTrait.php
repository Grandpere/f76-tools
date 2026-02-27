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

use App\Identity\UI\Security\IdentityEmailFlow;
use App\Identity\UI\Security\IdentityEmailFlowGuard;
use App\Identity\UI\Security\IdentityEmailFormPayload;
use App\Identity\UI\Security\IdentityFlashResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait IdentityEmailFlowControllerTrait
{
    protected function resolveEmailFlowPayloadOrFailureResponse(Request $request, IdentityEmailFlow $flow): IdentityEmailFormPayload|Response
    {
        $guardResult = $this->identityEmailFlowGuard()->guard($request, $flow);
        if (null !== $guardResult->failureFlashMessage) {
            return $this->identityFlashResponder()->warningToRoute($request, $flow->failureRoute(), $guardResult->failureFlashMessage);
        }

        return $guardResult->payload;
    }

    abstract protected function identityEmailFlowGuard(): IdentityEmailFlowGuard;

    abstract protected function identityFlashResponder(): IdentityFlashResponder;
}
