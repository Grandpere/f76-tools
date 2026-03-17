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

namespace App\Tests\Unit\Catalog\Nukacrypt;

use App\Catalog\Infrastructure\Nukacrypt\NukacryptRecordLookupRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NukacryptRecordLookupRepositoryTest extends TestCase
{
    public function testSearchReturnsResolvedRecords(): void
    {
        $repository = new NukacryptRecordLookupRepository(
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
            10,
            static function (string $url, array $headers, string $payloadJson, int $timeoutSeconds): array {
                TestCase::assertSame('https://api.nukacrypt.com/graphql', $url);
                TestCase::assertSame(10, $timeoutSeconds);
                TestCase::assertContains('Origin: https://nukacrypt.com', $headers);

                /** @var array<string, mixed> $payload */
                $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
                /** @var array<string, mixed> $variables */
                $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
                TestCase::assertSame('Plan: Vault 96 Jumpsuit', $variables['searchTerm'] ?? null);

                return [
                    'httpCode' => 200,
                    'body' => json_encode([
                        'data' => [
                            'esmRecords' => [
                                'success' => true,
                                'totalRecords' => 1,
                                'records' => [
                                    [
                                        'id' => '1e4e2f30-29bd-4261-a14d-126f7f5f131d',
                                        'name' => 'Plan: Vault 96 Jumpsuit',
                                        'editorId' => 'recipe_Armor_VaultSuit96_Underarmor',
                                        'formId' => '0031338f',
                                        'signature' => 'BOOK',
                                        'description' => null,
                                        'esmFileName' => null,
                                        'updatedAt' => null,
                                        'recordData' => ['price' => 50],
                                    ],
                                ],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $records = $repository->search('Plan: Vault 96 Jumpsuit');

        self::assertCount(1, $records);
        self::assertSame('0031338F', $records[0]->formId);
        self::assertSame('Plan: Vault 96 Jumpsuit', $records[0]->name);
        self::assertSame('recipe_Armor_VaultSuit96_Underarmor', $records[0]->editorId);
        self::assertSame('BOOK', $records[0]->signature);
        self::assertFalse($records[0]->hasErrors);
        self::assertSame(['price' => 50], $records[0]->recordData);
    }

    public function testSearchThrowsWhenSearchTermIsEmpty(): void
    {
        $repository = new NukacryptRecordLookupRepository(
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Search term cannot be empty');

        $repository->search('   ');
    }

    public function testSearchUsesConfiguredTimeoutAndStaticGameState(): void
    {
        $capturedTimeout = null;
        $capturedPayload = null;
        $capturedHeaders = null;

        $repository = new NukacryptRecordLookupRepository(
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
            7,
            static function (string $url, array $headers, string $payloadJson, int $timeoutSeconds) use (&$capturedTimeout, &$capturedPayload, &$capturedHeaders): array {
                TestCase::assertSame('https://api.nukacrypt.com/graphql', $url);
                $capturedTimeout = $timeoutSeconds;
                $capturedHeaders = $headers;
                $capturedPayload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

                return [
                    'httpCode' => 200,
                    'body' => json_encode([
                        'data' => [
                            'esmRecords' => [
                                'success' => true,
                                'totalRecords' => 0,
                                'records' => [],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $repository->search('Plan: Vault 96 Jumpsuit');

        self::assertSame(7, $capturedTimeout);
        self::assertIsArray($capturedPayload);
        self::assertIsArray($capturedHeaders);
        self::assertContains('Origin: https://nukacrypt.com', $capturedHeaders);
        self::assertContains('Referer: https://nukacrypt.com/', $capturedHeaders);
        self::assertContains('Accept: */*', $capturedHeaders);

        /** @var array<string, mixed> $variables */
        $variables = is_array($capturedPayload['variables'] ?? null) ? $capturedPayload['variables'] : [];
        /** @var array<string, mixed> $gameState */
        $gameState = is_array($variables['gameState'] ?? null) ? $variables['gameState'] : [];
        /** @var array<string, mixed> $page */
        $page = is_array($variables['page'] ?? null) ? $variables['page'] : [];
        /** @var array<string, mixed> $sort */
        $sort = is_array($page['sort'] ?? null) ? $page['sort'] : [];

        self::assertSame('dbdac593-fa03-4ad4-8251-36bc55d850b0', $gameState['gameId'] ?? null);
        self::assertSame('latest', $gameState['patchId'] ?? null);
        self::assertSame('primary', $gameState['fileId'] ?? null);
        self::assertSame(0, $page['offset'] ?? null);
        self::assertSame(25, $page['limit'] ?? null);
        self::assertSame('form_id', $sort['attribute'] ?? null);
        self::assertSame('ASC', $sort['direction'] ?? null);
    }

    public function testSearchByEditorIdReturnsResolvedRecords(): void
    {
        $repository = new NukacryptRecordLookupRepository(
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
            10,
            static function (string $url, array $headers, string $payloadJson, int $timeoutSeconds): array {
                TestCase::assertSame('https://api.nukacrypt.com/graphql', $url);
                TestCase::assertSame(10, $timeoutSeconds);
                TestCase::assertContains('Origin: https://nukacrypt.com', $headers);

                /** @var array<string, mixed> $payload */
                $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
                /** @var array<string, mixed> $variables */
                $variables = is_array($payload['variables'] ?? null) ? $payload['variables'] : [];
                TestCase::assertSame('recipe_Armor_VaultSuit96_Underarmor', $variables['editorId'] ?? null);

                return [
                    'httpCode' => 200,
                    'body' => json_encode([
                        'data' => [
                            'esmRecords' => [
                                'success' => true,
                                'totalRecords' => 1,
                                'records' => [
                                    [
                                        'id' => '1e4e2f30-29bd-4261-a14d-126f7f5f131d',
                                        'name' => 'Plan: Vault 96 Jumpsuit',
                                        'editorId' => 'recipe_Armor_VaultSuit96_Underarmor',
                                        'formId' => '0031338f',
                                        'signature' => 'BOOK',
                                        'description' => null,
                                        'esmFileName' => null,
                                        'updatedAt' => null,
                                        'recordData' => null,
                                    ],
                                ],
                            ],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ];
            },
        );

        $records = $repository->searchByEditorId('recipe_Armor_VaultSuit96_Underarmor');

        self::assertCount(1, $records);
        self::assertSame('0031338F', $records[0]->formId);
        self::assertSame('recipe_Armor_VaultSuit96_Underarmor', $records[0]->editorId);
    }

    public function testSearchThrowsWhenGraphqlBodyIsEmpty(): void
    {
        $repository = new NukacryptRecordLookupRepository(
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
            10,
            static fn (): array => ['httpCode' => 500, 'body' => ''],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nukacrypt GraphQL returned an empty body');

        $repository->search('Plan: Vault 96 Jumpsuit');
    }
}
