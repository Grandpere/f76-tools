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

use App\Catalog\Application\Minerva\MinervaRotationOverrideApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRefresher;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationRegenerationRepository;
use App\Catalog\Application\Minerva\MinervaRotationTimelineApplicationService;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/minerva-rotation')]
final class MinervaRotationController extends AbstractController
{
    use AdminRoleGuardControllerTrait;
    use AdminCsrfTokenValidatorTrait;

    public function __construct(
        private readonly MinervaRotationTimelineApplicationService $timelineService,
        private readonly MinervaRotationRegenerationApplicationService $regenerationService,
        private readonly MinervaRotationOverrideApplicationService $overrideService,
        private readonly MinervaRotationRefresher $minervaRotationRefresher,
        private readonly MinervaRotationRegenerationRepository $minervaRotationRegenerationRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_admin_minerva_rotation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->ensureAdminAccess();

        $now = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
        $fallbackFrom = $now->format('Y-m-01');
        $fallbackTo = $now->add(new DateInterval('P12M'))->format('Y-m-t');
        $defaultFrom = $this->normalizeDateInput($request->query->getString('from', '')) ?? $fallbackFrom;
        $defaultTo = $this->normalizeDateInput($request->query->getString('to', '')) ?? $fallbackTo;
        $timezone = new DateTimeZone('America/New_York');
        $from = $this->parseDate($defaultFrom, true, $timezone);
        $to = $this->parseDate($defaultTo, false, $timezone);
        $freshness = null;
        if ($from instanceof DateTimeImmutable && $to instanceof DateTimeImmutable) {
            $freshness = $this->minervaRotationRefresher->refresh($from, $to, true);
        }

        return $this->render('admin/minerva_rotation.html.twig', [
            'timeline' => $this->timelineService->buildTimeline(),
            'defaultFrom' => $defaultFrom,
            'defaultTo' => $defaultTo,
            'manualOverrides' => $this->buildManualRows(),
            'freshness' => $freshness,
            'latestGeneratedAt' => $this->minervaRotationRegenerationRepository->findLatestCreatedAtBySource(MinervaRotationSourceEnum::GENERATED),
            'latestManualAt' => $this->minervaRotationRegenerationRepository->findLatestCreatedAtBySource(MinervaRotationSourceEnum::MANUAL),
        ]);
    }

    #[Route('/regenerate', name: 'app_admin_minerva_rotation_regenerate', methods: ['POST'])]
    public function regenerate(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_minerva_rotation_regenerate')) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_csrf');

            return $this->redirectToMinervaPageWithRange($request);
        }

        $timezone = new DateTimeZone('America/New_York');
        $from = $this->parseDate((string) $request->request->get('from', ''), true, $timezone);
        $to = $this->parseDate((string) $request->request->get('to', ''), false, $timezone);
        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable || $to < $from) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_range');

            return $this->redirectToMinervaPageWithRange($request);
        }

        $result = $this->regenerationService->regenerate($from, $to);
        $this->addFlash('success', 'admin_minerva.flash.regenerated');
        $this->addFlash('success', $this->translator->trans('admin_minerva.flash.regenerated_summary', [
            '%deleted%' => (string) $result['deleted'],
            '%inserted%' => (string) $result['inserted'],
            '%skipped%' => (string) $result['skipped'],
        ]));

        return $this->redirectToMinervaPageWithRange($request);
    }

    #[Route('/refresh', name: 'app_admin_minerva_rotation_refresh', methods: ['POST'])]
    public function refreshCoverage(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_minerva_rotation_refresh')) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_csrf');

            return $this->redirectToMinervaPageWithRange($request);
        }

        $timezone = new DateTimeZone('America/New_York');
        $from = $this->parseDate((string) $request->request->get('from', ''), true, $timezone);
        $to = $this->parseDate((string) $request->request->get('to', ''), false, $timezone);
        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable || $to < $from) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_range');

            return $this->redirectToMinervaPageWithRange($request);
        }

        $dryRun = filter_var($request->request->get('dryRun', false), FILTER_VALIDATE_BOOL);
        $result = $this->minervaRotationRefresher->refresh($from, $to, $dryRun);

        if ($dryRun) {
            $coveredLabel = $result['covered']
                ? $this->translator->trans('admin_minerva.freshness_status_covered')
                : $this->translator->trans('admin_minerva.freshness_status_missing');
            $this->addFlash('success', $this->translator->trans('admin_minerva.flash.refresh_dry_run_summary', [
                '%expected%' => (string) $result['expectedWindows'],
                '%missing%' => (string) $result['missingWindows'],
                '%covered%' => $coveredLabel,
            ]));
        } elseif ($result['performed']) {
            $this->addFlash('success', 'admin_minerva.flash.refresh_performed');
            $this->addFlash('success', $this->translator->trans('admin_minerva.flash.refresh_performed_summary', [
                '%expected%' => (string) $result['expectedWindows'],
                '%missing%' => (string) $result['missingWindows'],
                '%deleted%' => (string) $result['deleted'],
                '%inserted%' => (string) $result['inserted'],
                '%skipped%' => (string) $result['skipped'],
            ]));
        } else {
            $this->addFlash('success', 'admin_minerva.flash.refresh_not_needed');
            $this->addFlash('success', $this->translator->trans('admin_minerva.flash.refresh_not_needed_summary', [
                '%expected%' => (string) $result['expectedWindows'],
                '%missing%' => (string) $result['missingWindows'],
            ]));
        }

        return $this->redirectToMinervaPageWithRange($request);
    }

    #[Route('/override/create', name: 'app_admin_minerva_rotation_override_create', methods: ['POST'])]
    public function createOverride(Request $request): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_minerva_rotation_override_create')) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        $timezone = new DateTimeZone('America/New_York');
        $location = trim((string) $request->request->get('location', ''));
        $listCycle = $this->parsePositiveInt($request->request->get('listCycle'));
        $startsAt = $this->parseDateTime((string) $request->request->get('startsAt', ''), $timezone);
        $endsAt = $this->parseDateTime((string) $request->request->get('endsAt', ''), $timezone);

        if ('' === $location || null === $listCycle || null === $startsAt || null === $endsAt || $endsAt < $startsAt) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_override');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        try {
            $this->overrideService->createManualOverride($location, $listCycle, $startsAt, $endsAt);
        } catch (InvalidArgumentException) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_override');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        $this->addFlash('success', 'admin_minerva.flash.override_created');

        return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
    }

    #[Route('/override/{id}/delete', name: 'app_admin_minerva_rotation_override_delete', methods: ['POST'])]
    public function deleteOverride(Request $request, int $id): RedirectResponse
    {
        $this->ensureAdminAccess();
        if (!$this->isValidToken($request, 'admin_minerva_rotation_override_delete_'.$id)) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        if (!$this->overrideService->deleteManualOverride($id)) {
            $this->addFlash('warning', 'admin_minerva.flash.override_not_found');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        $this->addFlash('success', 'admin_minerva.flash.override_deleted');

        return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
    }

    private function parseDate(string $value, bool $isStart, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }
        $suffix = $isStart ? '00:00:00' : '23:59:59';

        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%s %s', $trimmed, $suffix), $timezone) ?: null;
    }

    private function parseDateTime(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $trimmed = trim($value);
        if ('' === $trimmed) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $trimmed, $timezone) ?: null;
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (!is_scalar($value)) {
            return null;
        }
        $normalized = trim((string) $value);
        if ('' === $normalized || !ctype_digit($normalized)) {
            return null;
        }
        $int = (int) $normalized;

        return $int > 0 ? $int : null;
    }

    /**
     * @return list<array{id:int,location:string,listCycle:int,startsAt:string,endsAt:string}>
     */
    private function buildManualRows(): array
    {
        $rows = [];
        foreach ($this->overrideService->listManualOverrides() as $override) {
            $id = $override->getId();
            if (!is_int($id)) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'location' => $override->getLocation(),
                'listCycle' => $override->getListCycle(),
                'startsAt' => $override->getStartsAt()->format(DATE_ATOM),
                'endsAt' => $override->getEndsAt()->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    protected function csrfTokenManager(): CsrfTokenManagerInterface
    {
        return $this->csrfTokenManager;
    }

    private function redirectToMinervaPageWithRange(Request $request): RedirectResponse
    {
        $params = ['locale' => $request->getLocale()];
        $from = $this->normalizeDateInput((string) $request->request->get('from', ''));
        $to = $this->normalizeDateInput((string) $request->request->get('to', ''));
        if (is_string($from)) {
            $params['from'] = $from;
        }
        if (is_string($to)) {
            $params['to'] = $to;
        }

        return $this->redirectToRoute('app_admin_minerva_rotation', $params);
    }

    private function normalizeDateInput(string $value): ?string
    {
        $trimmed = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
        if (!$parsed instanceof DateTimeImmutable) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }
}
