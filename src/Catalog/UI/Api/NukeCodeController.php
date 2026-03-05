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

namespace App\Catalog\UI\Api;

use App\Catalog\Application\NukeCode\NukeCodeReadApplicationService;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class NukeCodeController extends AbstractController
{
    #[Route('/api/nuke-codes', name: 'app_api_nuke_codes', methods: ['GET'])]
    public function __invoke(NukeCodeReadApplicationService $readApplicationService): JsonResponse
    {
        $snapshot = $readApplicationService->getCurrent();
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $this->json([
            'alpha' => $snapshot->alpha,
            'bravo' => $snapshot->bravo,
            'charlie' => $snapshot->charlie,
            'expiresAt' => $snapshot->expiresAt->format(DATE_ATOM),
            'fetchedAt' => $snapshot->fetchedAt->format(DATE_ATOM),
            'secondsUntilReset' => max(0, $snapshot->expiresAt->getTimestamp() - $nowUtc->getTimestamp()),
            'stale' => $snapshot->stale,
        ]);
    }
}
