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

namespace App\Support\Application\Audit;

final class LatestMinervaRefreshSummaryApplicationService
{
    /**
     * @var list<string>
     */
    private const MINERVA_REFRESH_ACTIONS = [
        'minerva_refresh_dry_run',
        'minerva_refresh_performed',
        'minerva_refresh_not_needed',
    ];

    public function __construct(
        private readonly AuditLogReadRepository $auditLogReadRepository,
    ) {
    }

    /**
     * @return array{
     *     occurredAt:string,
     *     action:string,
     *     actorEmail:string,
     *     expectedWindows:int,
     *     missingWindows:int,
     *     deleted:int,
     *     inserted:int,
     *     skipped:int,
     *     dryRun:bool
     * }|null
     */
    public function resolve(): ?array
    {
        $latest = $this->auditLogReadRepository->findLatestByActions(self::MINERVA_REFRESH_ACTIONS);
        if (null === $latest) {
            return null;
        }

        $context = $latest->getContext() ?? [];

        return [
            'occurredAt' => $latest->getOccurredAt()->format(DATE_ATOM),
            'action' => $latest->getAction(),
            'actorEmail' => $latest->getActorUser()->getEmail(),
            'expectedWindows' => $this->contextInt($context, 'expectedWindows'),
            'missingWindows' => $this->contextInt($context, 'missingWindows'),
            'deleted' => $this->contextInt($context, 'deleted'),
            'inserted' => $this->contextInt($context, 'inserted'),
            'skipped' => $this->contextInt($context, 'skipped'),
            'dryRun' => true === ($context['dryRun'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function contextInt(array $context, string $key): int
    {
        $value = $context[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
