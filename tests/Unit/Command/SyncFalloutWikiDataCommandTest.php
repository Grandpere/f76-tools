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

namespace App\Tests\Unit\Command;

use App\Catalog\UI\Console\SyncFalloutWikiDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\KernelInterface;

final class SyncFalloutWikiDataCommandTest extends TestCase
{
    public function testSyncWritesPerPageFilesAndIndex(): void
    {
        $projectDir = sys_get_temp_dir().'/f76-sync-fallout-wiki-'.bin2hex(random_bytes(5));
        mkdir($projectDir, 0o777, true);

        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $query = is_array($options['query'] ?? null) ? $options['query'] : [];
            if (!isset($query['action'], $query['prop'], $query['page'])) {
                return new MockResponse('{"error":"missing query"}', ['http_code' => 400]);
            }

            if ('sections' === $query['prop']) {
                return new MockResponse((string) json_encode([
                    'parse' => [
                        'sections' => [
                            ['index' => '1', 'line' => 'Recipes'],
                        ],
                    ],
                ]));
            }

            if ('text' === $query['prop']) {
                return new MockResponse((string) json_encode([
                    'parse' => [
                        'text' => <<<HTML
                            <table>
                              <tr><th>Image</th><th>Name</th><th>Acquired</th><th>Value</th><th>Form ID</th></tr>
                              <tr>
                                <td><span title="Armor"></span></td>
                                <td><a href="/wiki/Recipe:Delbert%27s_Company_Tea">Recipe: Delbert's Company Tea</a></td>
                                <td><span title="World Object"></span></td>
                                <td>0 <span title="Bottle cap"></span></td>
                                <td>003A2021</td>
                              </tr>
                              <tr>
                                <td><span title="Weapon"></span></td>
                                <td><a href="/wiki/Plan:Alien_Disintegrator">Plan: Alien Disintegrator</a></td>
                                <td>Invaders from Beyond</td>
                                <td>2667 <span title="caps"></span></td>
                                <td>0062FE7E</td>
                              </tr>
                            </table>
                            HTML,
                    ],
                ]));
            }

            return new MockResponse('{"error":"unexpected"}', ['http_code' => 400]);
        });

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new SyncFalloutWikiDataCommand($kernel, $client);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--page' => ['Fallout_76_Recipes'],
            '--output-dir' => 'data/sources/fallout_wiki/plan_recipes/test_sync',
            '--no-delay' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $indexPath = $projectDir.'/data/sources/fallout_wiki/plan_recipes/test_sync/index.json';
        $recipesPath = $projectDir.'/data/sources/fallout_wiki/plan_recipes/test_sync/recipes.json';
        self::assertFileExists($indexPath);
        self::assertFileExists($recipesPath);

        $indexDecoded = json_decode((string) file_get_contents($indexPath), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($indexDecoded);
        self::assertSame(1, $indexDecoded['pages_count']);
        self::assertSame(2, $indexDecoded['resources_total']);

        $pageDecoded = json_decode((string) file_get_contents($recipesPath), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($pageDecoded);
        self::assertSame('Fallout_76_Recipes', $pageDecoded['page']);
        self::assertSame(2, $pageDecoded['resources_count']);
        self::assertIsArray($pageDecoded['resources']);

        $types = array_map(static function (mixed $row): string {
            if (!is_array($row)) {
                return '';
            }

            $type = $row['type'] ?? '';

            return is_scalar($type) ? (string) $type : '';
        }, $pageDecoded['resources']);
        sort($types);
        self::assertSame(['plan', 'recipe'], $types);

        $firstResource = $pageDecoded['resources'][0];
        self::assertIsArray($firstResource);
        self::assertIsArray($firstResource['columns'] ?? null);
        self::assertArrayHasKey('wiki_url', $firstResource['columns']);
        self::assertArrayHasKey('form_id', $firstResource['columns']);

        $detectedImageMetadata = false;
        $detectedValueCurrency = false;
        foreach ($pageDecoded['resources'] as $row) {
            if (!is_array($row) || !is_array($row['columns'] ?? null)) {
                continue;
            }

            $columns = $row['columns'];
            if (is_array($columns['image'] ?? null) && [] !== $columns['image']) {
                $detectedImageMetadata = true;
            }
            if (is_array($columns['value'] ?? null) && is_scalar($columns['value']['currency'] ?? null)) {
                $detectedValueCurrency = true;
            }
        }

        self::assertTrue($detectedImageMetadata);
        self::assertTrue($detectedValueCurrency);
    }

    public function testSyncFailsOnInvalidPayload(): void
    {
        $projectDir = sys_get_temp_dir().'/f76-sync-fallout-wiki-'.bin2hex(random_bytes(5));
        mkdir($projectDir, 0o777, true);

        $client = new MockHttpClient([
            new MockResponse('{"parse":{"sections":[{"index":"1","line":"Recipes"}]}}'),
            new MockResponse('{"parse":{"oops":"missing-text"}}'),
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($projectDir);

        $command = new SyncFalloutWikiDataCommand($kernel, $client);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--page' => ['Fallout_76_Recipes'],
            '--no-delay' => true,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('missing section html', strtolower($tester->getDisplay()));
    }
}
