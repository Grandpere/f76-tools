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

namespace App\Catalog\Application\Roadmap;

use DateTimeImmutable;

final readonly class RoadmapCalendarReadApplicationService
{
    public function __construct(
        private RoadmapCanonicalEventReadRepository $roadmapCanonicalEventReadRepository,
    ) {
    }

    /**
     * @return list<array{
     *     title: string,
     *     startsAt: DateTimeImmutable,
     *     endsAt: DateTimeImmutable,
     *     status: 'ongoing'|'upcoming'|'ended'
     * }>
     */
    public function listForLocale(string $locale): array
    {
        $normalizedLocale = strtolower(trim($locale));
        $now = new DateTimeImmutable('now');
        $rows = [];

        foreach ($this->roadmapCanonicalEventReadRepository->findAllOrdered() as $event) {
            $titles = [];
            foreach ($event->getTranslations() as $translation) {
                $translationLocale = strtolower($translation->getLocale());
                $title = trim($translation->getTitle());
                if ('' !== $title) {
                    $titles[$translationLocale] = $title;
                }
            }

            $title = $titles[$normalizedLocale]
                ?? $titles['en']
                ?? $titles['fr']
                ?? $titles['de']
                ?? $event->getTranslationKey();

            $rows[] = [
                'title' => $title,
                'startsAt' => $event->getStartsAt(),
                'endsAt' => $event->getEndsAt(),
                'status' => $this->resolveStatus($event->getStartsAt(), $event->getEndsAt(), $now),
            ];
        }

        return $rows;
    }

    /**
     * @return 'ongoing'|'upcoming'|'ended'
     */
    private function resolveStatus(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt, DateTimeImmutable $now): string
    {
        if ($now < $startsAt) {
            return 'upcoming';
        }

        if ($now > $endsAt) {
            return 'ended';
        }

        return 'ongoing';
    }
}
