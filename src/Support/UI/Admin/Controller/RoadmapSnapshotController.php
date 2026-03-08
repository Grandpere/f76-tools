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

namespace App\Support\UI\Admin\Controller;

use App\Catalog\Application\Roadmap\ApproveRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\GenerateRoadmapEventsFromSnapshotApplicationService;
use App\Catalog\Application\Roadmap\MergeRoadmapLocalesApplicationService;
use App\Catalog\Application\Roadmap\RoadmapCanonicalEventReadRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapEventEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{_locale<en|fr|de>}/admin/roadmap', defaults: ['_locale' => 'en'])]
final class RoadmapSnapshotController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;

    public function __construct(
        private readonly RoadmapSnapshotWriteRepository $roadmapSnapshotWriteRepository,
        private readonly RoadmapCanonicalEventReadRepository $roadmapCanonicalEventReadRepository,
        private readonly MergeRoadmapLocalesApplicationService $mergeRoadmapLocalesApplicationService,
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
        $canonicalRows = [];

        if (is_int($selectedId) && $selectedId > 0) {
            $selectedSnapshot = $this->roadmapSnapshotWriteRepository->findOneWithEventsById($selectedId);
        }
        if (!$selectedSnapshot instanceof RoadmapSnapshotEntity && [] !== $snapshots) {
            $fallbackId = $snapshots[0]->getId();
            $selectedSnapshot = is_int($fallbackId)
                ? $this->roadmapSnapshotWriteRepository->findOneWithEventsById($fallbackId)
                : $snapshots[0];
        }

        foreach ($this->roadmapCanonicalEventReadRepository->findAllOrdered() as $event) {
            $canonicalRows[] = $this->buildCanonicalRow($event);
        }

        $events = [];
        $selectedSnapshotImageUrl = null;
        if ($selectedSnapshot instanceof RoadmapSnapshotEntity) {
            $events = $selectedSnapshot->getEvents()->toArray();
            usort($events, static function (RoadmapEventEntity $a, RoadmapEventEntity $b): int {
                return $a->getSortOrder() <=> $b->getSortOrder();
            });
            $selectedSnapshotImageUrl = $this->generateUrl('app_admin_roadmap_snapshot_source_image', [
                '_locale' => $request->getLocale(),
                'id' => $selectedSnapshot->getId(),
            ]);
        }

        return $this->render('admin/roadmap_snapshots.html.twig', [
            'snapshots' => $snapshots,
            'selectedSnapshot' => $selectedSnapshot,
            'events' => $events,
            'selectedSnapshotImageUrl' => $selectedSnapshotImageUrl,
            'canonicalRows' => $canonicalRows,
        ]);
    }

    #[Route('/{id<\d+>}/source-image', name: 'app_admin_roadmap_snapshot_source_image', methods: ['GET'])]
    public function sourceImage(int $id): Response
    {
        $this->ensureAdminAccess();

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            throw $this->createNotFoundException('Snapshot not found.');
        }

        $imagePath = $this->resolveSnapshotImagePath($snapshot);
        if (null === $imagePath) {
            throw $this->createNotFoundException('Snapshot image not found.');
        }

        $response = new BinaryFileResponse($imagePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($imagePath));

        return $response;
    }

    #[Route('/{id<\d+>}/parse-events', name: 'app_admin_roadmap_snapshot_parse_events', methods: ['POST'])]
    public function parseEvents(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_parse_events_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        try {
            $parsed = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($id, false);
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $this->addFlash('success', 'admin_roadmap.flash.events_generated');
        $this->addFlash('success', sprintf('%d event(s).', count($parsed)));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
            'snapshot' => $id,
        ]);
    }

    #[Route('/merge-locales', name: 'app_admin_roadmap_merge_locales', methods: ['POST'])]
    public function mergeLocales(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_merge_locales')) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $frSnapshotId = $this->parsePositiveInt($request->request->get('fr_snapshot_id'));
        $enSnapshotId = $this->parsePositiveInt($request->request->get('en_snapshot_id'));
        $deSnapshotId = $this->parsePositiveInt($request->request->get('de_snapshot_id'));
        $dryRun = '1' === (string) $request->request->get('dry_run');

        if (null === $frSnapshotId || null === $enSnapshotId || null === $deSnapshotId) {
            $this->addFlash('warning', 'admin_roadmap.flash.merge_invalid_input');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        try {
            $result = $this->mergeRoadmapLocalesApplicationService->merge([
                'fr' => $frSnapshotId,
                'en' => $enSnapshotId,
                'de' => $deSnapshotId,
            ], $dryRun);
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $this->addFlash('success', 'admin_roadmap.flash.merge_success');
        $this->addFlash('success', sprintf(
            'Total=%d · High=%d · Medium=%d · Low=%d%s',
            $result->totalEvents,
            $result->highConfidenceEvents,
            $result->mediumConfidenceEvents,
            $result->lowConfidenceEvents,
            $dryRun ? ' (dry-run)' : '',
        ));
        foreach ($result->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'app_admin_roadmap_snapshot_delete', methods: ['POST'])]
    public function deleteSnapshot(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_delete_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            $this->addFlash('warning', 'admin_roadmap.flash.snapshot_not_found');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $this->roadmapSnapshotWriteRepository->delete($snapshot);
        $this->addFlash('success', 'admin_roadmap.flash.snapshot_deleted');

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
        ]);
    }

    #[Route('/{id<\d+>}/raw-text/save', name: 'app_admin_roadmap_snapshot_raw_text_save', methods: ['POST'])]
    public function saveRawText(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_raw_text_save_'.$id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            $this->addFlash('warning', 'admin_roadmap.flash.snapshot_not_found');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $rawTextInput = $request->request->get('raw_text');
        $rawText = is_scalar($rawTextInput) ? trim((string) $rawTextInput) : '';
        $snapshot->setRawText($rawText);
        $this->roadmapSnapshotWriteRepository->save($snapshot);
        $this->addFlash('success', 'admin_roadmap.flash.raw_text_saved');

        if ('1' === (string) $request->request->get('generate_events')) {
            try {
                $parsed = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($id, false);
                $this->addFlash('success', 'admin_roadmap.flash.events_generated');
                $this->addFlash('success', sprintf('%d event(s).', count($parsed)));
            } catch (RuntimeException $exception) {
                $this->addFlash('warning', $exception->getMessage());
            }
        }

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
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
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        try {
            $this->approveRoadmapSnapshotApplicationService->approve($id);
        } catch (RuntimeException $exception) {
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $this->addFlash('success', 'admin_roadmap.flash.snapshot_approved');

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
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
                '_locale' => $request->getLocale(),
                'snapshot' => $id,
            ]);
        }

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            $this->addFlash('warning', 'admin_roadmap.flash.snapshot_not_found');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
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
                ->setStartsAt($startsAt)
                ->setEndsAt($endsAt);
            ++$updated;
        }

        $this->roadmapSnapshotWriteRepository->save($snapshot);
        $this->addFlash('success', sprintf('%d event(s) updated.', $updated));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
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

    private function parsePositiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }

        $raw = (string) $value;
        if (!ctype_digit($raw)) {
            return null;
        }

        $parsed = (int) $raw;

        return $parsed > 0 ? $parsed : null;
    }

    /**
     * @return array{
     *     translationKey: string,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable,
     *     confidenceScore: int,
     *     translations: array{fr: ?string, en: ?string, de: ?string},
     *     missingLocales: list<string>
     * }
     */
    private function buildCanonicalRow(RoadmapCanonicalEventEntity $event): array
    {
        $translations = [
            'fr' => null,
            'en' => null,
            'de' => null,
        ];

        foreach ($event->getTranslations() as $translation) {
            $locale = strtolower($translation->getLocale());
            if (array_key_exists($locale, $translations)) {
                $translations[$locale] = $translation->getTitle();
            }
        }

        $missingLocales = [];
        foreach ($translations as $locale => $title) {
            if (!is_string($title) || '' === trim($title)) {
                $missingLocales[] = $locale;
            }
        }

        return [
            'translationKey' => $event->getTranslationKey(),
            'startsAt' => $event->getStartsAt(),
            'endsAt' => $event->getEndsAt(),
            'confidenceScore' => $event->getConfidenceScore(),
            'translations' => $translations,
            'missingLocales' => $missingLocales,
        ];
    }

    private function resolveSnapshotImagePath(RoadmapSnapshotEntity $snapshot): ?string
    {
        $configuredPath = trim($snapshot->getSourceImagePath());
        if ('' === $configuredPath) {
            return null;
        }

        $projectDirParameter = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDirParameter) || '' === $projectDirParameter) {
            return null;
        }
        $projectDir = $projectDirParameter;
        $projectDirReal = realpath($projectDir) ?: $projectDir;

        $candidates = [];
        if (str_starts_with($configuredPath, '/')) {
            $candidates[] = $configuredPath;
        } else {
            $candidates[] = $projectDir.'/'.ltrim($configuredPath, '/');
        }

        $dockerProjectPrefix = '/var/www/html/';
        if (str_starts_with($configuredPath, $dockerProjectPrefix)) {
            $candidates[] = $projectDir.'/'.ltrim(substr($configuredPath, strlen($dockerProjectPrefix)), '/');
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!is_file($candidate) || !is_readable($candidate)) {
                continue;
            }

            $resolvedPath = realpath($candidate);
            if (false === $resolvedPath) {
                continue;
            }

            if (!str_starts_with($resolvedPath, rtrim($projectDirReal, '/').'/')) {
                continue;
            }

            return $resolvedPath;
        }

        return null;
    }
}
