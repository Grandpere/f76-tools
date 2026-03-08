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

namespace App\Catalog\UI\Web;

use App\Identity\Domain\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class NukeCodeController extends AbstractController
{
    #[Route('/{_locale<en|fr|de>}/nuke-codes', name: 'app_nuke_codes', methods: ['GET'], defaults: ['_locale' => 'en'])]
    #[Route('/nuke-codes', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $this->render('nuke_codes/index.html.twig', [
            'username' => $user->getEmail(),
            'apiNukeCodesUrl' => $this->generateUrl('app_api_nuke_codes'),
        ]);
    }
}
