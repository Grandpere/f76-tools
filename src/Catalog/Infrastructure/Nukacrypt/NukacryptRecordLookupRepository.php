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
use Closure;
use RuntimeException;

final class NukacryptRecordLookupRepository implements NukacryptRecordLookup
{
    private const ALLOWED_HOST = 'api.nukacrypt.com';
    private const FO76_GAME_ID = 'dbdac593-fa03-4ad4-8251-36bc55d850b0';
    private const DEFAULT_PATCH_ID = 'latest';
    private const DEFAULT_FILE_ID = 'primary';
    private const DEFAULT_BROWSER_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36';

    public function __construct(
        private readonly string $graphqlUrl,
        private readonly string $userAgent,
        private readonly int $timeoutSeconds = 10,
        private readonly ?Closure $curlExecutor = null,
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

        return $this->searchInternal($normalizedSearchTerm, null, $signatures);
    }

    /**
     * @param list<string> $signatures
     *
     * @return list<NukacryptRecord>
     */
    public function searchByEditorId(string $editorId, array $signatures = ['BOOK']): array
    {
        $normalizedEditorId = trim($editorId);
        if ('' === $normalizedEditorId) {
            throw new RuntimeException('Editor ID cannot be empty.');
        }

        return $this->searchInternal(null, $normalizedEditorId, $signatures);
    }

    /**
     * @param list<string> $signatures
     *
     * @return list<NukacryptRecord>
     */
    private function searchInternal(?string $searchTerm, ?string $editorId, array $signatures): array
    {
        $variables = [
            'gameState' => [
                'gameId' => self::FO76_GAME_ID,
                'patchId' => self::DEFAULT_PATCH_ID,
                'fileId' => self::DEFAULT_FILE_ID,
            ],
            'page' => [
                'offset' => 0,
                'limit' => 25,
                'sort' => [
                    'attribute' => 'form_id',
                    'direction' => 'ASC',
                ],
            ],
        ];

        if (null !== $searchTerm) {
            $variables['searchTerm'] = $searchTerm;
        }

        if (null !== $editorId) {
            $variables['editorId'] = $editorId;
        }

        $normalizedSignatures = $this->normalizeSignatures($signatures);
        if ([] !== $normalizedSignatures) {
            $variables['signatures'] = $normalizedSignatures;
        }

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
                          fileMeta
                        }
                      }
                    }
                GRAPHQL,
            $variables,
        );

        $data = $payload['data'] ?? null;
        $recordsNode = is_array($data) ? ($data['esmRecords'] ?? null) : null;
        if (!is_array($recordsNode) || true !== ($recordsNode['success'] ?? false)) {
            throw new RuntimeException(sprintf('Nukacrypt esmRecords search failed for "%s".%s', $searchTerm ?? $editorId ?? 'unknown', $this->describeGraphqlErrors($payload)));
        }

        $records = $recordsNode['records'] ?? null;
        if (!is_array($records)) {
            throw new RuntimeException(sprintf('Nukacrypt esmRecords payload is missing records for "%s".', $searchTerm ?? $editorId ?? 'unknown'));
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
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $query, array $variables): array
    {
        $this->assertGraphqlUrlAllowed();

        $payloadJson = json_encode([
            'query' => $query,
            'variables' => [] === $variables ? (object) [] : $variables,
        ], JSON_THROW_ON_ERROR);

        $result = $this->executeCurl(
            $this->graphqlUrl,
            $this->requestHeaders(),
            $payloadJson,
            max(1, $this->timeoutSeconds),
        );

        if ('' === $result['body']) {
            throw new RuntimeException(sprintf('Nukacrypt GraphQL returned an empty body (HTTP %d).', $result['httpCode']));
        }

        $decoded = json_decode($result['body'], true);
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Nukacrypt GraphQL returned invalid JSON (HTTP %d).', $result['httpCode']));
        }

        /** @var array<string, mixed> $payload */
        $payload = $decoded;

        return $payload;
    }

    /**
     * @param list<string> $headers
     *
     * @return array{httpCode:int, body:string}
     */
    private function executeCurl(string $url, array $headers, string $payloadJson, int $timeoutSeconds): array
    {
        if ($this->curlExecutor instanceof Closure) {
            /** @var array{httpCode:int, body:string} $result */
            $result = ($this->curlExecutor)($url, $headers, $payloadJson, $timeoutSeconds);

            return $result;
        }

        $curl = curl_init($url);
        if (false === $curl) {
            throw new RuntimeException('Unable to initialize cURL for Nukacrypt GraphQL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payloadJson,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        $body = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (false === $body) {
            throw new RuntimeException('Unable to query Nukacrypt GraphQL: '.$error);
        }

        if (!is_string($body)) {
            throw new RuntimeException('Unable to query Nukacrypt GraphQL: unexpected cURL response type.');
        }

        return [
            'httpCode' => $httpCode,
            'body' => $body,
        ];
    }

    /**
     * @return list<string>
     */
    private function requestHeaders(): array
    {
        return [
            'Content-Type: application/json',
            'Accept: */*',
            'Accept-Language: fr-FR,fr;q=0.6',
            'Cache-Control: no-cache',
            'Origin: https://nukacrypt.com',
            'Pragma: no-cache',
            'Priority: u=1, i',
            'Referer: https://nukacrypt.com/',
            'Sec-CH-UA: "Not(A:Brand";v="8", "Chromium";v="144", "Brave";v="144"',
            'Sec-CH-UA-Mobile: ?0',
            'Sec-CH-UA-Platform: "macOS"',
            'Sec-Fetch-Dest: empty',
            'Sec-Fetch-Mode: cors',
            'Sec-Fetch-Site: same-site',
            'Sec-GPC: 1',
            'User-Agent: '.$this->resolveUserAgent(),
        ];
    }

    private function resolveUserAgent(): string
    {
        $userAgent = trim($this->userAgent);

        if ('' !== $userAgent && str_starts_with($userAgent, 'Mozilla/')) {
            return $userAgent;
        }

        return self::DEFAULT_BROWSER_USER_AGENT;
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
        if (is_array($value) && $this->isStringKeyedArray($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        return null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function describeGraphqlErrors(array $payload): string
    {
        $errors = $payload['errors'] ?? null;
        if (!is_array($errors) || [] === $errors) {
            return '';
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
            return '';
        }

        return ' Errors: '.implode(' | ', $messages);
    }

    private function assertGraphqlUrlAllowed(): void
    {
        $parts = parse_url($this->graphqlUrl);
        if (!is_array($parts)) {
            throw new RuntimeException('Nukacrypt GraphQL URL is invalid.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ('https' !== $scheme || self::ALLOWED_HOST !== $host) {
            throw new RuntimeException('Nukacrypt GraphQL URL must target https://api.nukacrypt.com.');
        }
    }
}
