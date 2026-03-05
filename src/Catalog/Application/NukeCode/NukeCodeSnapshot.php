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

namespace App\Catalog\Application\NukeCode;

use DateTimeImmutable;

final readonly class NukeCodeSnapshot
{
    public function __construct(
        public string $alpha,
        public string $bravo,
        public string $charlie,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $fetchedAt,
        public bool $stale = false,
    ) {
    }

    /**
     * @return array{alpha: string, bravo: string, charlie: string, expiresAt: string, fetchedAt: string, stale: bool}
     */
    public function toArray(): array
    {
        return [
            'alpha' => $this->alpha,
            'bravo' => $this->bravo,
            'charlie' => $this->charlie,
            'expiresAt' => $this->expiresAt->format(DATE_ATOM),
            'fetchedAt' => $this->fetchedAt->format(DATE_ATOM),
            'stale' => $this->stale,
        ];
    }

    /**
     * @param array{alpha: string, bravo: string, charlie: string, expiresAt: string, fetchedAt: string, stale: bool} $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['alpha'],
            $payload['bravo'],
            $payload['charlie'],
            new DateTimeImmutable($payload['expiresAt']),
            new DateTimeImmutable($payload['fetchedAt']),
            $payload['stale'],
        );
    }

    public function asStale(): self
    {
        if ($this->stale) {
            return $this;
        }

        return new self(
            $this->alpha,
            $this->bravo,
            $this->charlie,
            $this->expiresAt,
            $this->fetchedAt,
            true,
        );
    }
}
