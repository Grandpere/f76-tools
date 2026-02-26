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

namespace App\Controller\Admin;

use App\Catalog\Application\Minerva\MinervaRotationRegenerationApplicationService;
use App\Catalog\Application\Minerva\MinervaRotationTimelineApplicationService;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admin/minerva-rotation')]
final class MinervaRotationController extends AbstractController
{
    public function __construct(
        private readonly MinervaRotationTimelineApplicationService $timelineService,
        private readonly MinervaRotationRegenerationApplicationService $regenerationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'app_admin_minerva_rotation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $now = new DateTimeImmutable('now', new DateTimeZone('America/New_York'));
        $defaultFrom = $now->format('Y-m-01');
        $defaultTo = $now->add(new \DateInterval('P12M'))->format('Y-m-t');

        return $this->render('admin/minerva_rotation.html.twig', [
            'timeline' => $this->timelineService->buildTimeline(),
            'defaultFrom' => $defaultFrom,
            'defaultTo' => $defaultTo,
        ]);
    }

    #[Route('/regenerate', name: 'app_admin_minerva_rotation_regenerate', methods: ['POST'])]
    public function regenerate(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_csrf_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admin_minerva_rotation_regenerate', $token))) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_csrf');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        $timezone = new DateTimeZone('America/New_York');
        $from = $this->parseDate((string) $request->request->get('from', ''), true, $timezone);
        $to = $this->parseDate((string) $request->request->get('to', ''), false, $timezone);
        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable || $to < $from) {
            $this->addFlash('warning', 'admin_minerva.flash.invalid_range');

            return $this->redirectToRoute('app_admin_minerva_rotation', ['locale' => $request->getLocale()]);
        }

        $result = $this->regenerationService->regenerate($from, $to);
        $this->addFlash('success', 'admin_minerva.flash.regenerated');
        $this->addFlash('success', sprintf(
            'Deleted: %d · Inserted: %d',
            $result['deleted'],
            $result['inserted'],
        ));

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
}
