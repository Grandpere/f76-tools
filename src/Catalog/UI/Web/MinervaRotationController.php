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

use App\Catalog\Application\Minerva\MinervaRotationTimelineApplicationService;
use App\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class MinervaRotationController extends AbstractController
{
    public function __construct(
        private readonly MinervaRotationTimelineApplicationService $timelineApplicationService,
    ) {
    }

    #[Route('/minerva-rotation', name: 'app_minerva_rotation', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $this->render('minerva/rotation.html.twig', [
            'timeline' => $this->timelineApplicationService->buildTimeline(),
            'username' => $user->getEmail(),
        ]);
    }
}
