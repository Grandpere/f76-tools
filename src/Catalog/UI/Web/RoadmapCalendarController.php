<?php

declare(strict_types=1);

namespace App\Catalog\UI\Web;

use App\Catalog\Application\Roadmap\RoadmapCalendarReadApplicationService;
use App\Identity\Domain\Entity\UserEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class RoadmapCalendarController extends AbstractController
{
    public function __construct(
        private readonly RoadmapCalendarReadApplicationService $roadmapCalendarReadApplicationService,
    ) {
    }

    #[Route('/roadmap-calendar', name: 'app_roadmap_calendar', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof UserEntity) {
            throw new AccessDeniedException('User must be authenticated.');
        }

        return $this->render('roadmap/calendar.html.twig', [
            'username' => $user->getEmail(),
            'rows' => $this->roadmapCalendarReadApplicationService->listForLocale($request->getLocale()),
        ]);
    }
}
