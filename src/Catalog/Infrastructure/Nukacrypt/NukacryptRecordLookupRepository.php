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

namespace App\Catalog\Infrastructure\Nukacrypt;

use App\Catalog\Application\Nukacrypt\NukacryptRecord;
use App\Catalog\Application\Nukacrypt\NukacryptRecordLookup;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NukacryptRecordLookupRepository implements NukacryptRecordLookup
{
    private const ALLOWED_HOST = 'api.nukacrypt.com';
    private const FO76_SHORTNAME = 'FO76';
    private const DEFAULT_PATCH_ID = 'latest';
    private const DEFAULT_FILE_ID = 'SeventySix.esm';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $graphqlUrl,
        private readonly string $userAgent,
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    /**
     * @param list<string> $signatures
     *
     * @return list<NukacryptRecord>
     */
    public function search(string $searchTerm, array $signatures = ['BOOK']): array
    {
        $normalizedSearchTerm = trim($searchTerm);
        if ('' === $normalizedSearchTerm) {
            throw new RuntimeException('Search term cannot be empty.');
        }

        $gameState = $this->fetchFo76GameState();
        $payload = $this->requestJson(
            <<<'GRAPHQL'
                    query ($gameState: GameStateArgs, $searchTerm: String, $editorId: String, $signatures: [String], $page: Criteria, $ids: [String]) {
                      esmRecords(searchTerm: $searchTerm, editorId: $editorId, signatures: $signatures, gameStateArgs: $gameState, page: $page, ids: $ids) {
                        success
                        totalRecords
                        records {
                          id
                          name
                          description
                          signature
                          editorId
                          formId
                          esmFileName
                          updatedAt
                          recordData
                        }
                      }
                    }
                GRAPHQL,
            [
                'searchTerm' => $normalizedSearchTerm,
                'signatures' => $this->normalizeSignatures($signatures),
                'gameState' => $gameState,
            ],
        );

        $data = $payload['data'] ?? null;
        $recordsNode = is_array($data) ? ($data['esmRecords'] ?? null) : null;
        if (!is_array($recordsNode) || true !== ($recordsNode['success'] ?? false)) {
            throw new RuntimeException(sprintf('Nukacrypt esmRecords search failed for "%s".%s', $normalizedSearchTerm, $this->describeGraphqlErrors($payload)));
        }

        $records = $recordsNode['records'] ?? null;
        if (!is_array($records)) {
            throw new RuntimeException(sprintf('Nukacrypt esmRecords payload is missing records for "%s".', $normalizedSearchTerm));
        }

        $resolved = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $recordData = $this->normalizeRecordData($record['recordData'] ?? null);

            $resolved[] = new NukacryptRecord(
                formId: strtoupper($this->normalizeNullableString($record['formId'] ?? null) ?? ''),
                name: $this->normalizeNullableString($record['name'] ?? null),
                editorId: $this->normalizeNullableString($record['editorId'] ?? null),
                signature: $this->normalizeNullableString($record['signature'] ?? null),
                description: $this->normalizeNullableString($record['description'] ?? null),
                esmFileName: $this->normalizeNullableString($record['esmFileName'] ?? null),
                updatedAt: $this->normalizeNullableString($record['updatedAt'] ?? null),
                hasErrors: false,
                recordData: $recordData,
            );
        }

        return array_values(array_filter(
            $resolved,
            static fn (NukacryptRecord $record): bool => '' !== $record->formId,
        ));
    }

    /**
     * @return array{gameId:string, patchId:string, fileId:string}
     */
    private function fetchFo76GameState(): array
    {
        $payload = $this->requestJson(
            <<<'GRAPHQL'
                    query {
                      games {
                        id
                        name
                        shortname
                        patches {
                          esmFiles {
                            fileName
                          }
                        }
                      }
                    }
                GRAPHQL,
            [],
        );

        $data = $payload['data'] ?? null;
        $games = is_array($data) ? ($data['games'] ?? null) : null;
        if (!is_array($games)) {
            throw new RuntimeException(sprintf('Nukacrypt GraphQL payload is missing games field.%s', $this->describeGraphqlErrors($payload)));
        }

        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }

            if (self::FO76_SHORTNAME !== $this->normalizeNullableString($game['shortname'] ?? null)) {
                continue;
            }

            $gameId = $this->normalizeNullableString($game['id'] ?? null);
            if (null === $gameId) {
                break;
            }

            return [
                'gameId' => $gameId,
                'patchId' => self::DEFAULT_PATCH_ID,
                'fileId' => self::DEFAULT_FILE_ID,
            ];
        }

        throw new RuntimeException('Unable to resolve FO76 game state from Nukacrypt GraphQL.');
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $query, array $variables): array
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
                    'query' => $query,
                    'variables' => [] === $variables ? (object) [] : $variables,
                ],
            ]);

            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);

            return $payload;
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('Unable to query Nukacrypt GraphQL: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param list<string> $signatures
     *
     * @return list<string>
     */
    private function normalizeSignatures(array $signatures): array
    {
        $normalized = [];
        foreach ($signatures as $signature) {
            $value = strtoupper(trim($signature));
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<mixed, mixed> $value
     */
    private function isStringKeyedArray(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRecordData(mixed $value): ?array
    {
        if (!is_array($value) || !$this->isStringKeyedArray($value)) {
            return null;
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
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

    /**
     * @param array<string, mixed> $payload
     */
    private function describeGraphqlErrors(array $payload): string
    {
        $errors = $payload['errors'] ?? null;
        if (!is_array($errors) || [] === $errors) {
            $topLevelKeys = implode(', ', array_keys($payload));

            return '' === $topLevelKeys ? '' : sprintf(' Top-level keys: %s.', $topLevelKeys);
        }

        $messages = [];
        foreach ($errors as $error) {
            if (!is_array($error)) {
                continue;
            }

            $message = $this->normalizeNullableString($error['message'] ?? null);
            if (null !== $message) {
                $messages[] = $message;
            }
        }

        if ([] === $messages) {
            return ' GraphQL returned errors without message.';
        }

        return ' GraphQL errors: '.implode(' | ', $messages);
    }
}
