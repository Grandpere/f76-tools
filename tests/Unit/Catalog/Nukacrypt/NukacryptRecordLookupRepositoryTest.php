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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NukacryptRecordLookupRepositoryTest extends TestCase
{
    public function testSearchReturnsResolvedRecords(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'games' => [
                        [
                            'id' => 'dbdac593-fa03-4ad4-8251-36bc55d850b0',
                            'name' => 'Fallout 76',
                            'shortname' => 'FO76',
                            'patches' => [],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
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
            ], JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptRecordLookupRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
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

    public function testSearchThrowsWhenFo76GameCannotBeResolved(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'games' => [],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptRecordLookupRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve FO76 game state');

        $repository->search('Plan: Vault 96 Jumpsuit');
    }

    public function testSearchThrowsWhenSearchTermIsEmpty(): void
    {
        $repository = new NukacryptRecordLookupRepository(
            new MockHttpClient([]),
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Search term cannot be empty');

        $repository->search('   ');
    }

    public function testSearchUsesConfiguredTimeout(): void
    {
        $capturedTimeout = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedTimeout): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.nukacrypt.com/graphql', $url);
            $capturedTimeout = $options['timeout'] ?? null;

            /** @var int $calls */
            static $calls = 0;
            ++$calls;

            if (1 === $calls) {
                return new MockResponse(json_encode([
                    'data' => [
                        'games' => [
                            [
                                'id' => 'dbdac593-fa03-4ad4-8251-36bc55d850b0',
                                'name' => 'Fallout 76',
                                'shortname' => 'FO76',
                                'patches' => [],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode([
                'data' => [
                    'esmRecords' => [
                        'success' => true,
                        'totalRecords' => 0,
                        'records' => [],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $repository = new NukacryptRecordLookupRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
            7,
        );
        $repository->search('Plan: Vault 96 Jumpsuit');

        self::assertEquals(7, $capturedTimeout);
    }

    public function testSearchByEditorIdReturnsResolvedRecords(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'data' => [
                    'games' => [
                        [
                            'id' => 'dbdac593-fa03-4ad4-8251-36bc55d850b0',
                            'name' => 'Fallout 76',
                            'shortname' => 'FO76',
                            'patches' => [],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
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
            ], JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptRecordLookupRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $records = $repository->searchByEditorId('recipe_Armor_VaultSuit96_Underarmor');

        self::assertCount(1, $records);
        self::assertSame('0031338F', $records[0]->formId);
        self::assertSame('recipe_Armor_VaultSuit96_Underarmor', $records[0]->editorId);
    }
}
