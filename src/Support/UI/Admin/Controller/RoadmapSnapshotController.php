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
use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotApplicationService;
use App\Catalog\Application\Roadmap\CreateRoadmapSnapshotInput;
use App\Catalog\Application\Roadmap\GenerateRoadmapEventsFromSnapshotApplicationService;
use App\Catalog\Application\Roadmap\MergeRoadmapLocalesApplicationService;
use App\Catalog\Application\Roadmap\Ocr\ProcessRoadmapSnapshotOcrMessage;
use App\Catalog\Application\Roadmap\RoadmapCanonicalEventReadRepository;
use App\Catalog\Application\Roadmap\RoadmapParsedEvent;
use App\Catalog\Application\Roadmap\RoadmapParsedEventsValidator;
use App\Catalog\Application\Roadmap\RoadmapSeasonExtractor;
use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapCanonicalEventEntity;
use App\Catalog\Domain\Entity\RoadmapEventEntity;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapOcrProcessingStatusEnum;
use App\Identity\Domain\Entity\UserEntity;
use App\Support\Domain\Entity\AdminAuditLogEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route('/{_locale<en|fr|de>}/admin/roadmap', defaults: ['_locale' => 'en'])]
final class RoadmapSnapshotController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;
    private const CANONICAL_ROWS_CACHE_KEY_PREFIX = 'admin_roadmap.canonical_rows.v2.';

    public function __construct(
        private readonly RoadmapSnapshotWriteRepository $roadmapSnapshotWriteRepository,
        private readonly RoadmapCanonicalEventReadRepository $roadmapCanonicalEventReadRepository,
        private readonly RoadmapSeasonRepository $roadmapSeasonRepository,
        private readonly RoadmapSeasonExtractor $roadmapSeasonExtractor,
        private readonly CreateRoadmapSnapshotApplicationService $createRoadmapSnapshotApplicationService,
        private readonly MessageBusInterface $messageBus,
        private readonly MergeRoadmapLocalesApplicationService $mergeRoadmapLocalesApplicationService,
        private readonly GenerateRoadmapEventsFromSnapshotApplicationService $generateRoadmapEventsFromSnapshotApplicationService,
        private readonly RoadmapParsedEventsValidator $roadmapParsedEventsValidator,
        private readonly ApproveRoadmapSnapshotApplicationService $approveRoadmapSnapshotApplicationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_roadmap_snapshots', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureAdminAccess();

        $seasons = $this->roadmapSeasonRepository->findAllOrderedBySeasonNumberDesc();
        $activeSeason = $this->roadmapSeasonRepository->findActive();
        $seasonFilterId = $this->parsePositiveInt($request->query->get('season'));
        $selectedSeason = is_int($seasonFilterId) ? $this->roadmapSeasonRepository->findOneById($seasonFilterId) : null;
        $qualityFilter = $this->normalizeSnapshotQualityFilter($request->query->get('quality'));

        $snapshots = $this->roadmapSnapshotWriteRepository->findRecent(30, $selectedSeason);
        $snapshotQualityById = $this->buildSnapshotQualityById($snapshots);
        $mergedSnapshotIds = $this->findMergedSnapshotIds();
        if (is_string($qualityFilter)) {
            $snapshots = array_values(array_filter(
                $snapshots,
                function (RoadmapSnapshotEntity $snapshot) use ($snapshotQualityById, $qualityFilter): bool {
                    $id = $snapshot->getId();

                    return is_int($id)
                        && isset($snapshotQualityById[$id])
                        && $snapshotQualityById[$id]['level'] === $qualityFilter;
                },
            ));
        }
        $selectedIdRaw = $request->query->get('snapshot');
        $selectedId = is_scalar($selectedIdRaw) && ctype_digit((string) $selectedIdRaw) ? (int) $selectedIdRaw : null;
        $selectedSnapshot = null;
        $canonicalRows = [];
        $canonicalSeason = $selectedSeason ?? $activeSeason;

        if (is_int($selectedId) && $selectedId > 0) {
            $selectedSnapshot = $this->roadmapSnapshotWriteRepository->findOneWithEventsById($selectedId);
        }
        if (!$selectedSnapshot instanceof RoadmapSnapshotEntity && [] !== $snapshots) {
            $fallbackId = $snapshots[0]->getId();
            $selectedSnapshot = is_int($fallbackId)
                ? $this->roadmapSnapshotWriteRepository->findOneWithEventsById($fallbackId)
                : $snapshots[0];
        }

        /** @var list<array{
         *     startsAt: DateTimeImmutable,
         *     endsAt: DateTimeImmutable,
         *     seasonNumber: int|null,
         *     confidenceScore: int,
         *     missingLocales: list<string>,
         *     translations: array{fr?: string, en?: string, de?: string}
         * }> $canonicalRows
         */
        $canonicalSeasonId = $canonicalSeason instanceof RoadmapSeasonEntity ? $canonicalSeason->getId() : null;
        $canonicalCacheKey = self::CANONICAL_ROWS_CACHE_KEY_PREFIX.(is_int($canonicalSeasonId) ? (string) $canonicalSeasonId : 'none');
        $canonicalRows = $this->cache->get($canonicalCacheKey, function (ItemInterface $item) use ($canonicalSeason): array {
            $item->expiresAfter(60);

            $rows = [];
            foreach ($this->roadmapCanonicalEventReadRepository->findAllOrdered($canonicalSeason) as $event) {
                $rows[] = $this->buildCanonicalRow($event);
            }

            return $rows;
        });

        $mergeSnapshotContext = $this->resolveMergeSnapshotContext($request);

        $events = [];
        $selectedSnapshotImageUrl = null;
        $selectedSnapshotQuality = null;
        if ($selectedSnapshot instanceof RoadmapSnapshotEntity) {
            $events = $selectedSnapshot->getEvents()->toArray();
            usort($events, static function (RoadmapEventEntity $a, RoadmapEventEntity $b): int {
                return $a->getSortOrder() <=> $b->getSortOrder();
            });
            $selectedSnapshotImageUrl = $this->generateUrl('app_admin_roadmap_snapshot_source_image', [
                '_locale' => $request->getLocale(),
                'id' => $selectedSnapshot->getId(),
            ]);
            $selectedSnapshotId = $selectedSnapshot->getId();
            if (is_int($selectedSnapshotId)) {
                $selectedSnapshotQuality = $this->assessSnapshotQuality($selectedSnapshot, $selectedSnapshotId);
            }
        }

        return $this->render('admin/roadmap_snapshots.html.twig', [
            'snapshots' => $snapshots,
            'selectedSnapshot' => $selectedSnapshot,
            'events' => $events,
            'selectedSnapshotImageUrl' => $selectedSnapshotImageUrl,
            'canonicalRows' => $canonicalRows,
            'seasons' => $seasons,
            'activeSeason' => $activeSeason,
            'selectedSeason' => $selectedSeason,
            'qualityFilter' => $qualityFilter,
            'mergeSnapshotContext' => $mergeSnapshotContext,
            'snapshotQualityById' => $snapshotQualityById,
            'selectedSnapshotQuality' => $selectedSnapshotQuality,
            'mergedSnapshotIds' => $mergedSnapshotIds,
        ]);
    }

    #[Route('/upload', name: 'app_admin_roadmap_snapshot_upload', methods: ['POST'])]
    public function uploadSnapshot(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();

        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_upload')) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $localeInput = strtolower(trim((string) $request->request->get('locale', 'en')));
        if (!in_array($localeInput, ['fr', 'en', 'de'], true)) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_invalid_locale');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }
        $preprocessMode = strtolower(trim((string) $request->request->get('preprocess', 'layout-bw')));
        if (!in_array($preprocessMode, ['none', 'grayscale', 'bw', 'strong-bw', 'layout-bw'], true)) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_invalid_preprocess');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $file = $request->files->get('image');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_missing_file');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $mimeType = (string) ($file->getMimeType() ?? '');
        if (!str_starts_with($mimeType, 'image/')) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_invalid_file_type');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $projectDirParameter = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDirParameter) || '' === $projectDirParameter) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_storage_error');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $relativeDirectory = 'var/data/roadmap_uploads';
        $absoluteDirectory = rtrim($projectDirParameter, '/').'/'.$relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0o775, true) && !is_dir($absoluteDirectory)) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_storage_error');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        try {
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
            $extension = '' !== trim((string) $extension) ? strtolower((string) $extension) : 'bin';
            $filename = sprintf('roadmap_%s_%s_%s.%s', $localeInput, new DateTimeImmutable()->format('Ymd_His'), bin2hex(random_bytes(4)), $extension);

            $storedFile = $file->move($absoluteDirectory, $filename);
            $absolutePath = $storedFile->getPathname();
            $relativePath = $relativeDirectory.'/'.$filename;

            $snapshot = $this->createRoadmapSnapshotApplicationService->create(
                new CreateRoadmapSnapshotInput(
                    $localeInput,
                    $absolutePath,
                    'pending',
                    0.0,
                    '',
                ),
            );
            $snapshot->setSourceImagePath($relativePath);
            $snapshot->setOcrPreprocessMode($preprocessMode);
            $snapshot->setOcrProcessingStatus(RoadmapOcrProcessingStatusEnum::QUEUED);
            $snapshot->setOcrProcessingError(null);
            $this->roadmapSnapshotWriteRepository->save($snapshot);

            $snapshotId = $snapshot->getId();
            if (!is_int($snapshotId)) {
                throw new RuntimeException('Snapshot ID is missing after persistence.');
            }

            $this->messageBus->dispatch(new ProcessRoadmapSnapshotOcrMessage(
                $snapshotId,
                $localeInput,
                $preprocessMode,
            ));
        } catch (Throwable $exception) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_failed');
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $snapshotId = $snapshot->getId();
        $this->addFlash('success', 'admin_roadmap.flash.upload_queued');
        $this->addFlash('success', $this->translator->trans('admin_roadmap.flash.upload_preprocess_used', [
            '%mode%' => $preprocessMode,
        ]));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
            'snapshot' => is_int($snapshotId) ? $snapshotId : null,
        ]);
    }

    #[Route('/import-json', name: 'app_admin_roadmap_snapshot_import_json', methods: ['POST'])]
    public function importJsonSnapshot(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();

        if (!$this->isValidToken($request, 'admin_roadmap_snapshot_import_json')) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $localeInput = strtolower(trim((string) $request->request->get('locale', 'en')));
        if (!in_array($localeInput, ['fr', 'en', 'de'], true)) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_invalid_locale');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $rawJson = trim((string) $request->request->get('json_payload', ''));
        if ('' === $rawJson) {
            $this->addFlash('warning', 'admin_roadmap.flash.json_missing_payload');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        try {
            $decodedPayload = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->addFlash('warning', 'admin_roadmap.flash.json_invalid_payload');
            $this->addFlash('warning', $exception->getMessage());

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        if (!is_array($decodedPayload)) {
            $this->addFlash('warning', 'admin_roadmap.flash.json_invalid_payload');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $eventsPayload = $decodedPayload['events'] ?? null;
        if (!is_array($eventsPayload) || [] === $eventsPayload) {
            $this->addFlash('warning', 'admin_roadmap.flash.json_missing_events');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $projectDirParameter = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDirParameter) || '' === $projectDirParameter) {
            $this->addFlash('warning', 'admin_roadmap.flash.upload_storage_error');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
            ]);
        }

        $seasonNumber = $this->parsePositiveInt($request->request->get('season_number'));
        if (!is_int($seasonNumber)) {
            $seasonNumber = $this->parsePositiveInt($decodedPayload['season'] ?? null);
        }

        $season = null;
        if (is_int($seasonNumber)) {
            $season = $this->roadmapSeasonRepository->findOneBySeasonNumber($seasonNumber);
            if (!$season instanceof RoadmapSeasonEntity) {
                $season = new RoadmapSeasonEntity()
                    ->setSeasonNumber($seasonNumber)
                    ->setTitle(sprintf('Season %d', $seasonNumber));
                $this->roadmapSeasonRepository->save($season);
            }
        }

        $normalizedEvents = [];
        foreach (array_values($eventsPayload) as $index => $row) {
            if (!is_array($row)) {
                $this->addFlash('warning', $this->translator->trans('admin_roadmap.flash.json_invalid_event', [
                    '%index%' => (string) ($index + 1),
                ]));

                return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                    '_locale' => $request->getLocale(),
                ]);
            }

            $titleValue = $row['title'] ?? null;
            $startsAtValue = $row['date_start'] ?? null;
            $endsAtValue = $row['date_end'] ?? null;
            $title = is_string($titleValue) ? trim($titleValue) : '';
            $startsAt = $this->parseJsonDate(is_string($startsAtValue) ? $startsAtValue : '');
            $endsAt = $this->parseJsonDate(is_string($endsAtValue) ? $endsAtValue : '');
            if ('' === $title || !$startsAt instanceof DateTimeImmutable || !$endsAt instanceof DateTimeImmutable || $endsAt < $startsAt) {
                $this->addFlash('warning', $this->translator->trans('admin_roadmap.flash.json_invalid_event', [
                    '%index%' => (string) ($index + 1),
                ]));

                return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                    '_locale' => $request->getLocale(),
                ]);
            }

            $normalizedEvents[] = [
                'title' => $title,
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
            ];
        }

        usort($normalizedEvents, static function (array $a, array $b): int {
            $startsAtSort = $a['startsAt'] <=> $b['startsAt'];
            if (0 !== $startsAtSort) {
                return $startsAtSort;
            }

            $endsAtSort = $a['endsAt'] <=> $b['endsAt'];
            if (0 !== $endsAtSort) {
                return $endsAtSort;
            }

            return $a['title'] <=> $b['title'];
        });

        $sourceImagePath = '';
        $sourceImageHash = hash('sha256', $rawJson);
        $optionalImage = $request->files->get('image');
        if ($optionalImage instanceof UploadedFile && $optionalImage->isValid()) {
            $mimeType = (string) ($optionalImage->getMimeType() ?? '');
            if (!str_starts_with($mimeType, 'image/')) {
                $this->addFlash('warning', 'admin_roadmap.flash.upload_invalid_file_type');

                return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                    '_locale' => $request->getLocale(),
                ]);
            }

            $relativeDirectory = 'var/data/roadmap_uploads';
            $absoluteDirectory = rtrim($projectDirParameter, '/').'/'.$relativeDirectory;
            if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0o775, true) && !is_dir($absoluteDirectory)) {
                $this->addFlash('warning', 'admin_roadmap.flash.upload_storage_error');

                return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                    '_locale' => $request->getLocale(),
                ]);
            }

            $extension = $optionalImage->guessExtension() ?: $optionalImage->getClientOriginalExtension();
            $extension = '' !== trim((string) $extension) ? strtolower((string) $extension) : 'bin';
            $filename = sprintf('roadmap_json_%s_%s_%s.%s', $localeInput, new DateTimeImmutable()->format('Ymd_His'), bin2hex(random_bytes(4)), $extension);
            $storedFile = $optionalImage->move($absoluteDirectory, $filename);
            $absolutePath = $storedFile->getPathname();
            $sourceImagePath = $relativeDirectory.'/'.$filename;
            $fileHash = hash_file('sha256', $absolutePath);
            if (is_string($fileHash)) {
                $sourceImageHash = $fileHash;
            }
        }

        $snapshot = new RoadmapSnapshotEntity()
            ->setLocale($localeInput)
            ->setSourceImagePath($sourceImagePath)
            ->setSourceImageHash($sourceImageHash)
            ->setOcrProvider('manual.json')
            ->setOcrConfidence(1.0)
            ->setRawText(json_encode($decodedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $rawJson)
            ->setSeason($season);

        $sortOrder = 1;
        foreach ($normalizedEvents as $eventData) {
            $snapshot->addEvent(
                new RoadmapEventEntity()
                    ->setLocale($localeInput)
                    ->setTitle($eventData['title'])
                    ->setStartsAt($eventData['startsAt'])
                    ->setEndsAt($eventData['endsAt'])
                    ->setSortOrder($sortOrder),
            );
            ++$sortOrder;
        }

        $this->roadmapSnapshotWriteRepository->save($snapshot);
        $snapshotId = $snapshot->getId();
        $this->addFlash('success', $this->translator->trans('admin_roadmap.flash.json_import_success', [
            '%count%' => (string) count($normalizedEvents),
        ]));

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
            'snapshot' => is_int($snapshotId) ? $snapshotId : null,
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
        $this->addParseQualityFlashes($id, $parsed);

        return $this->redirectToRoute('app_admin_roadmap_snapshots', [
            '_locale' => $request->getLocale(),
            'snapshot' => $id,
        ]);
    }

    #[Route('/merge-locales', name: 'app_admin_roadmap_merge_locales', methods: ['POST'])]
    public function mergeLocales(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();
        $frSnapshotId = $this->parsePositiveInt($request->request->get('fr_snapshot_id'));
        $enSnapshotId = $this->parsePositiveInt($request->request->get('en_snapshot_id'));
        $deSnapshotId = $this->parsePositiveInt($request->request->get('de_snapshot_id'));

        if (!$this->isValidToken($request, 'admin_roadmap_merge_locales')) {
            $this->addFlash('warning', 'admin_roadmap.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'fr_snapshot_id' => $frSnapshotId,
                'en_snapshot_id' => $enSnapshotId,
                'de_snapshot_id' => $deSnapshotId,
            ]);
        }
        $dryRun = '1' === (string) $request->request->get('dry_run');

        if (null === $frSnapshotId || null === $enSnapshotId || null === $deSnapshotId) {
            $this->addFlash('warning', 'admin_roadmap.flash.merge_invalid_input');

            return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                '_locale' => $request->getLocale(),
                'fr_snapshot_id' => $frSnapshotId,
                'en_snapshot_id' => $enSnapshotId,
                'de_snapshot_id' => $deSnapshotId,
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
                'fr_snapshot_id' => $frSnapshotId,
                'en_snapshot_id' => $enSnapshotId,
                'de_snapshot_id' => $deSnapshotId,
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
        if (!$dryRun) {
            $this->persistRoadmapMergeAuditLog($frSnapshotId, $enSnapshotId, $deSnapshotId, $result->totalEvents);
            $this->cache->delete(self::CANONICAL_ROWS_CACHE_KEY_PREFIX.'none');
            foreach ($this->roadmapSeasonRepository->findAllOrderedBySeasonNumberDesc() as $season) {
                $seasonId = $season->getId();
                if (is_int($seasonId)) {
                    $this->cache->delete(self::CANONICAL_ROWS_CACHE_KEY_PREFIX.(string) $seasonId);
                }
            }
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

        $imagePath = $this->resolveSnapshotImagePath($snapshot);
        if (is_string($imagePath)) {
            @unlink($imagePath);
        }

        if ($this->isSnapshotUsedInMerge($id)) {
            $this->addFlash('warning', 'admin_roadmap.flash.snapshot_deleted_used_in_merge');
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
        $seasonNumber = $this->roadmapSeasonExtractor->extractSeasonNumber($rawText);
        if (is_int($seasonNumber) && $seasonNumber > 0) {
            $season = $this->roadmapSeasonRepository->findOneBySeasonNumber($seasonNumber);
            if (!$season instanceof RoadmapSeasonEntity) {
                $season = new RoadmapSeasonEntity()
                    ->setSeasonNumber($seasonNumber)
                    ->setTitle(sprintf('Season %d', $seasonNumber));
                $this->roadmapSeasonRepository->save($season);
            }
            $snapshot->setSeason($season);
        }
        $this->roadmapSnapshotWriteRepository->save($snapshot);
        $this->addFlash('success', 'admin_roadmap.flash.raw_text_saved');

        if ('1' === (string) $request->request->get('generate_events')) {
            try {
                $parsed = $this->generateRoadmapEventsFromSnapshotApplicationService->generate($id, false);
                $this->addFlash('success', 'admin_roadmap.flash.events_generated');
                $this->addFlash('success', sprintf('%d event(s).', count($parsed)));
                $this->addParseQualityFlashes($id, $parsed);
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

        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($id);
        if ($snapshot instanceof RoadmapSnapshotEntity) {
            $parsed = $this->resolveSnapshotParsedEvents($snapshot, $id);
            $validation = $this->roadmapParsedEventsValidator->validate(
                $parsed,
                $snapshot->getLocale(),
                $snapshot->getRawText(),
            );
            if ($validation->hasErrors()) {
                $this->addFlash('warning', 'admin_roadmap.flash.snapshot_quality_blocking');
                foreach ($validation->errors as $error) {
                    $this->addFlash('warning', $error);
                }

                return $this->redirectToRoute('app_admin_roadmap_snapshots', [
                    '_locale' => $request->getLocale(),
                    'snapshot' => $id,
                ]);
            }
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

    private function parseJsonDate(string $value): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $trimmed.' 18:00:00');

        return $parsed instanceof DateTimeImmutable ? $parsed : null;
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

    private function normalizeSnapshotQualityFilter(mixed $raw): ?string
    {
        if (!is_scalar($raw)) {
            return null;
        }

        $value = strtolower(trim((string) $raw));
        if (!in_array($value, ['ok', 'warn', 'error'], true)) {
            return null;
        }

        return $value;
    }

    /**
     * @param list<RoadmapParsedEvent> $parsed
     */
    private function addParseQualityFlashes(int $snapshotId, array $parsed): void
    {
        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($snapshotId);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            return;
        }

        $validation = $this->roadmapParsedEventsValidator->validate(
            $parsed,
            $snapshot->getLocale(),
            $snapshot->getRawText(),
        );

        foreach ($validation->warnings as $warning) {
            $this->addFlash('warning', $warning);
        }
        foreach ($validation->errors as $error) {
            $this->addFlash('warning', $error);
        }
    }

    /**
     * @param list<RoadmapSnapshotEntity> $snapshots
     *
     * @return array<int, array{level: string, errors: int, warnings: int}>
     */
    private function buildSnapshotQualityById(array $snapshots): array
    {
        $qualityById = [];

        foreach ($snapshots as $snapshot) {
            $snapshotId = $snapshot->getId();
            if (!is_int($snapshotId)) {
                continue;
            }

            $assessment = $this->assessSnapshotQuality($snapshot, $snapshotId);
            $qualityById[$snapshotId] = [
                'level' => $assessment['level'],
                'errors' => count($assessment['errors']),
                'warnings' => count($assessment['warnings']),
            ];
        }

        return $qualityById;
    }

    /**
     * @return array{
     *     level: string,
     *     errors: list<string>,
     *     warnings: list<string>
     * }
     */
    private function assessSnapshotQuality(RoadmapSnapshotEntity $snapshot, int $snapshotId): array
    {
        $processingStatus = $snapshot->getOcrProcessingStatus();
        if (RoadmapOcrProcessingStatusEnum::QUEUED === $processingStatus || RoadmapOcrProcessingStatusEnum::PROCESSING === $processingStatus) {
            return [
                'level' => 'warn',
                'errors' => [],
                'warnings' => [$this->translator->trans('admin_roadmap.snapshot_quality_waiting_ocr')],
            ];
        }
        if (RoadmapOcrProcessingStatusEnum::FAILED === $processingStatus) {
            $error = trim((string) $snapshot->getOcrProcessingError());
            if ('' === $error) {
                $error = $this->translator->trans('admin_roadmap.snapshot_quality_failed_ocr');
            }

            return [
                'level' => 'error',
                'errors' => [$error],
                'warnings' => [],
            ];
        }

        try {
            $parsedEvents = $this->resolveSnapshotParsedEvents($snapshot, $snapshotId);
            $validation = $this->roadmapParsedEventsValidator->validate(
                $parsedEvents,
                $snapshot->getLocale(),
                $snapshot->getRawText(),
            );
            $level = [] !== $validation->errors
                ? 'error'
                : ([] !== $validation->warnings ? 'warn' : 'ok');

            return [
                'level' => $level,
                'errors' => $validation->errors,
                'warnings' => $validation->warnings,
            ];
        } catch (RuntimeException $exception) {
            return [
                'level' => 'error',
                'errors' => [$exception->getMessage()],
                'warnings' => [],
            ];
        }
    }

    /**
     * @return list<RoadmapParsedEvent>
     */
    private function resolveSnapshotParsedEvents(RoadmapSnapshotEntity $snapshot, int $snapshotId): array
    {
        if ($snapshot->getEvents()->count() > 0) {
            $persisted = $snapshot->getEvents()->toArray();
            usort($persisted, static function (RoadmapEventEntity $a, RoadmapEventEntity $b): int {
                $sort = $a->getSortOrder() <=> $b->getSortOrder();
                if (0 !== $sort) {
                    return $sort;
                }

                return $a->getStartsAt() <=> $b->getStartsAt();
            });

            $resolved = [];
            foreach ($persisted as $event) {
                $resolved[] = new RoadmapParsedEvent(
                    $event->getTitle(),
                    $event->getStartsAt(),
                    $event->getEndsAt(),
                );
            }

            return $resolved;
        }

        return $this->generateRoadmapEventsFromSnapshotApplicationService->generate($snapshotId, true);
    }

    /**
     * @return array{
     *     translationKey: string,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable,
     *     seasonNumber: int|null,
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
            'seasonNumber' => $event->getSeason()?->getSeasonNumber(),
            'confidenceScore' => $event->getConfidenceScore(),
            'translations' => $translations,
            'missingLocales' => $missingLocales,
        ];
    }

    /**
     * @return array{
     *     fr: array{snapshotId: int, seasonNumber: ?int, qualityLevel: string, errors: int, warnings: int}|null,
     *     en: array{snapshotId: int, seasonNumber: ?int, qualityLevel: string, errors: int, warnings: int}|null,
     *     de: array{snapshotId: int, seasonNumber: ?int, qualityLevel: string, errors: int, warnings: int}|null
     * }
     */
    private function resolveMergeSnapshotContext(Request $request): array
    {
        $context = [
            'fr' => null,
            'en' => null,
            'de' => null,
        ];

        foreach (['fr', 'en', 'de'] as $locale) {
            $snapshotId = $this->parsePositiveInt($request->query->get($locale.'_snapshot_id'));
            if (!is_int($snapshotId)) {
                continue;
            }
            $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($snapshotId);
            if (!$snapshot instanceof RoadmapSnapshotEntity) {
                $context[$locale] = [
                    'snapshotId' => $snapshotId,
                    'seasonNumber' => null,
                    'qualityLevel' => 'error',
                    'errors' => 1,
                    'warnings' => 0,
                ];
                continue;
            }

            $assessment = $this->assessSnapshotQuality($snapshot, $snapshotId);
            $context[$locale] = [
                'snapshotId' => $snapshotId,
                'seasonNumber' => $snapshot->getSeason()?->getSeasonNumber(),
                'qualityLevel' => $assessment['level'],
                'errors' => count($assessment['errors']),
                'warnings' => count($assessment['warnings']),
            ];
        }

        return $context;
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

    /**
     * @return array<int, true>
     */
    private function findMergedSnapshotIds(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('a.context')
            ->from(AdminAuditLogEntity::class, 'a')
            ->where('a.action = :action')
            ->setParameter('action', 'roadmap_merge_locales')
            ->orderBy('a.occurredAt', 'DESC')
            ->setMaxResults(250)
            ->getQuery()
            ->getArrayResult();

        $snapshotIds = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['context']) || !is_array($row['context'])) {
                continue;
            }

            $context = $row['context'];
            foreach (['frSnapshotId', 'enSnapshotId', 'deSnapshotId'] as $key) {
                $value = $context[$key] ?? null;
                if (is_int($value) && $value > 0) {
                    $snapshotIds[$value] = true;
                }
            }
        }

        return $snapshotIds;
    }

    private function isSnapshotUsedInMerge(int $snapshotId): bool
    {
        $mergedSnapshotIds = $this->findMergedSnapshotIds();

        return isset($mergedSnapshotIds[$snapshotId]);
    }

    private function persistRoadmapMergeAuditLog(int $frSnapshotId, int $enSnapshotId, int $deSnapshotId, int $totalEvents): void
    {
        $actor = $this->getUser();
        if (!$actor instanceof UserEntity) {
            return;
        }

        $this->entityManager->persist(
            new AdminAuditLogEntity()
                ->setActorUser($actor)
                ->setAction('roadmap_merge_locales')
                ->setContext([
                    'frSnapshotId' => $frSnapshotId,
                    'enSnapshotId' => $enSnapshotId,
                    'deSnapshotId' => $deSnapshotId,
                    'totalEvents' => $totalEvents,
                ])
                ->setOccurredAt(new DateTimeImmutable()),
        );
        $this->entityManager->flush();
    }
}
