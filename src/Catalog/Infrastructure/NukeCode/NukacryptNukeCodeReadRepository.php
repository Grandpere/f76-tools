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

namespace App\Catalog\Infrastructure\NukeCode;

use App\Catalog\Application\NukeCode\NukeCodeReadRepository;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NukacryptNukeCodeReadRepository implements NukeCodeReadRepository
{
    private const ALLOWED_HOST = 'api.nukacrypt.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $graphqlUrl,
        private readonly string $userAgent,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    /**
     * @return array{alpha: string, bravo: string, charlie: string}
     */
    public function fetchCurrent(): array
    {
        $this->assertGraphqlUrlAllowed();

        try {
            $response = $this->httpClient->request('POST', $this->graphqlUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => $this->userAgent,
                ],
                'timeout' => max(1, $this->timeoutSeconds),
                'json' => [
                    'query' => 'query CurrentNukeCodes { nukeCodes { alpha bravo charlie } }',
                ],
            ]);
            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('Unable to fetch nukecodes from Nukacrypt GraphQL.', 0, $exception);
        }

        $data = $payload['data'] ?? null;
        $nukeCodes = is_array($data) ? ($data['nukeCodes'] ?? null) : null;
        if (!is_array($nukeCodes)) {
            throw new RuntimeException('Nukacrypt GraphQL payload is missing nukeCodes field.');
        }

        $alpha = $this->normalizeCode($nukeCodes['alpha'] ?? null);
        $bravo = $this->normalizeCode($nukeCodes['bravo'] ?? null);
        $charlie = $this->normalizeCode($nukeCodes['charlie'] ?? null);

        if ('' === $alpha || '' === $bravo || '' === $charlie) {
            throw new RuntimeException('Nukacrypt GraphQL payload contains empty nuke codes.');
        }

        return [
            'alpha' => $alpha,
            'bravo' => $bravo,
            'charlie' => $charlie,
        ];
    }

    private function normalizeCode(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (!is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function assertGraphqlUrlAllowed(): void
    {
        $url = trim($this->graphqlUrl);
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Nukacrypt GraphQL URL is invalid.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if ('https' !== $scheme || self::ALLOWED_HOST !== $host) {
            throw new RuntimeException('Nukacrypt GraphQL URL must target https://api.nukacrypt.com.');
        }
    }
}
