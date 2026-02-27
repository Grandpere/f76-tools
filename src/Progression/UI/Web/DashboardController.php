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

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly PlayerReadApplicationService $playerReadApplicationService,
    ) {
    }

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
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

        return $this->render('dashboard/index.html.twig', [
            'apiPlayersUrl' => $this->generateUrl('api_players_index'),
            'apiPlayersBaseUrl' => $this->generateUrl('api_players_index'),
            'activePlayerId' => $activePlayerId,
            'userId' => $user->getId(),
            'username' => $user->getEmail(),
        ]);
    }
}
