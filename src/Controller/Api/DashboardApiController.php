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

namespace App\Controller\Api;

use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardApiController extends AbstractController
{
    #[Route('/api/dashboard', name: 'app_api_dashboard', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'title' => 'Symfony WebApp + API',
            'updatedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            'cards' => [
                ['label' => 'Statut', 'value' => 'OK'],
                ['label' => 'Backend', 'value' => 'Symfony 8'],
                ['label' => 'Database', 'value' => 'PostgreSQL'],
            ],
        ]);
    }
}
