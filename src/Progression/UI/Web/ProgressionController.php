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

namespace App\Progression\UI\Web;

use App\Identity\Domain\Entity\UserEntity;
use App\Progression\Application\Player\PlayerReadApplicationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProgressionController extends AbstractController
{
    public function __construct(
        private readonly PlayerReadApplicationService $playerReadApplicationService,
    ) {
    }

    #[Route('/{_locale<en|fr|de>}/', name: 'app_home', methods: ['GET'], defaults: ['_locale' => 'en'])]
    #[Route('/{_locale<en|fr|de>}/progression', name: 'app_progression', methods: ['GET'], defaults: ['_locale' => 'en'])]
    #[Route('/', methods: ['GET'])]
    #[Route('/progression', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        $players = $this->playerReadApplicationService->listForUser($user);
        $activePlayerId = null;
        if ([] !== $players) {
            $activePlayerId = $players[0]->getPublicId();
        }

        return $this->render('progression/index.html.twig', [
            'username' => $user->getEmail(),
            'apiPlayersUrl' => $this->generateUrl('api_players_index'),
            'apiPlayersBaseUrl' => $this->generateUrl('api_players_index'),
            'activePlayerId' => $activePlayerId,
            'storageKey' => sprintf('f76:item-catalog:ui:%d', (int) ($user->getId() ?? 0)),
        ]);
    }
}
