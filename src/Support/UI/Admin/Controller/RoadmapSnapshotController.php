<?php

declare(strict_types=1);

namespace App\Support\UI\Admin\Controller;

use App\Catalog\Application\Roadmap\ApproveRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\GenerateRoadmapEventsFromSnapshotApplicationService;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapEventEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/roadmap')]
final class RoadmapSnapshotController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;

    public function __construct(
        private readonly RoadmapSnapshotWriteRepository $roadmapSnapshotWriteRepository,
        private readonly GenerateRoadmapEventsFromSnapshotApplicationService $generateRoadmapEventsFromSnapshotApplicationService,
        private readonly ApproveRoadmapSnapshotApplicationService $approveRoadmapSnapshotApplicationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'app_admin_roadmap_snapshots', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureAdminAccess();

        $snapshots = $this->roadmapSnapshotWriteRepository->findRecent(30);
        $selectedIdRaw = $request->query->get('snapshot');
        $selectedId = is_scalar($selectedIdRaw) && ctype_digit((string) $selectedIdRaw) ? (int) $selectedIdRaw : null;
        $selectedSnapshot = null;

        if (is_int($selectedId) && $selectedId > 0) {
            $selectedSnapshot = $this->roadmapSnapshotWriteRepository->findOneById($selectedId);
        }
        if (!$selectedSnapshot instanceof RoadmapSnapshotEntity && [] !== $snapshots) {
            $selectedSnapshot = $snapshots[0];
        }

        $events = [];
        if ($selectedSnapshot instanceof RoadmapSnapshotEntity) {
            $events = $selectedSnapshot->getEvents()->toArray();
            usort($events, static function (RoadmapEventEntity $a, RoadmapEventEntity $b): int {
                return $a->getSortOrder() <=> $b->getSortOrder();
            });
        }

        return $this->render('admin/roadmap_snapshots.html.twig', [
            'snapshots' => $snapshots,
            'selectedSnapshot' => $selectedSnapshot,
            'events' => $events,
        ]);
    }

    #[Route('/{id<\d+>}/parse-events', name: 'app_admin_roadmap_snapshot_parse_events', methods: ['POST'])]
    public function parseEvents(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_parse_events_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        try {
            $parsed = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($id, false);
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $this->addFlash('success', 'admin_roadmap.flash.events_generated');
        $this->addFlash('success', sprintf('%d event(s).', count($parsed)));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            'locale' => $request->getLocale(),
            'snapshot' => $id,
        ]);
    }

    #[Route('/{id<\d+>}/approve', name: 'app_admin_roadmap_snapshot_approve', methods: ['POST'])]
    public function approve(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_approve_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        try {
            $this->approveRoadmapSnapshotApplicationService->approve($id);
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $this->addFlash('success', 'admin_roadmap.flash.snapshot_approved');

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            'locale' => $request->getLocale(),
            'snapshot' => $id,
        ]);
    }

    #[Route('/{id<\d+>}/events/save', name: 'app_admin_roadmap_snapshot_events_save', methods: ['POST'])]
    public function saveEvents(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_events_save_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            $this->addFlash('warning', 'admin_roadmap.flash.snapshot_not_found');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                'locale' => $request->getLocale(),
            ]);
        }

        /** @var array<string, array<string, string>> $eventsPayload */
        $eventsPayload = $request->request->all('events');
        $eventById = [];
        foreach ($snapshot->getEvents() as $event) {
            $eventId = $event->getId();
            if (is_int($eventId)) {
                $eventById[(string) $eventId] = $event;
            }
        }

        $updated = 0;
        foreach ($eventsPayload as $eventId => $payload) {
            if (!isset($eventById[$eventId])) {
                continue;
            }
            $event = $eventById[$eventId];
            $title = trim((string) ($payload['title'] ?? ''));
            $startsAt = $this->parseDateTimeLocal((string) ($payload['startsAt'] ?? ''));
            $endsAt = $this->parseDateTimeLocal((string) ($payload['endsAt'] ?? ''));

            if ('' === $title || !$startsAt instanceof DateTimeImmutable || !$endsAt instanceof DateTimeImmutable || $endsAt < $startsAt) {
                continue;
            }

            $event
                ->setTitle($title)
                ->setEventType($this->normalizeNullableString($payload['eventType'] ?? null))
                ->setNotes($this->normalizeNullableString($payload['notes'] ?? null))
                ->setStartsAt($startsAt)
                ->setEndsAt($endsAt);
            ++$updated;
        }

        $this->roadmapSnapshotWriteRepository->save($snapshot);
        $this->addFlash('success', sprintf('%d event(s) updated.', $updated));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            'locale' => $request->getLocale(),
            'snapshot' => $id,
        ]);
    }

    protected function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager;
    }

    private function parseDateTimeLocal(string $value): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $trimmed);
        if (!$parsed instanceof DateTimeImmutable) {
            return null;
        }

        return $parsed;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }
}

