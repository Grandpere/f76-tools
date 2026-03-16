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

use App\Catalog\UI\Console\SyncFandomDataCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\KernelInterface;

final class SyncFandomDataCommandTest extends TestCase
{
    public function testSyncWritesPerPageFilesAndIndexWithTableColumns(): void
    {
        $projectDir = sys_get_temp_dir().'/f76-sync-fandom-'.bin2hex(random_bytes(5));
        mkdir($projectDir, 0777, true);

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
  <tr><th>Item</th><th>Weight</th><th>Value</th><th>Form ID</th><th>Containers</th></tr>
  <tr>
    <td><a href="/wiki/Recipe:Berry_Mentats">Recipe: Berry Mentats</a> <span title="Wastelanders"></span></td>
    <td>0.25</td>
    <td>35 <span title="Bottle cap"></span></td>
    <td>00123ABC</td>
    <td>Yes</td>
  </tr>
  <tr>
    <td><a href="/wiki/Plan:Laser_Gun">Plan: Laser Gun</a></td>
    <td>0.1</td>
    <td>50</td>
    <td>00AAAAAA</td>
    <td>-</td>
  </tr>
</table>
HTML,
                    ],
                ]));
            }

            return new MockResponse('{"error":"unexpected"}', ['http_code' => 400]);
        });

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getProjectDir')
            ->willReturn($projectDir);

        $command = new SyncFandomDataCommand($kernel, $client);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--page' => ['Fallout_76_recipes'],
            '--output-dir' => 'data/plan_recipes_pages/test_sync',
            '--no-delay' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $indexPath = $projectDir.'/data/plan_recipes_pages/test_sync/index.json';
        $recipesPath = $projectDir.'/data/plan_recipes_pages/test_sync/recipes.json';
        self::assertFileExists($indexPath);
        self::assertFileExists($recipesPath);

        $indexDecoded = json_decode((string) file_get_contents($indexPath), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($indexDecoded);
        self::assertSame(1, $indexDecoded['pages_count']);
        self::assertSame(2, $indexDecoded['resources_total']);
        self::assertIsArray($indexDecoded['pages']);
        self::assertCount(1, $indexDecoded['pages']);

        $pageDecoded = json_decode((string) file_get_contents($recipesPath), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($pageDecoded);
        self::assertSame('Fallout_76_recipes', $pageDecoded['page']);
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

        self::assertIsArray($pageDecoded['resources'][0]);
        $firstResource = $pageDecoded['resources'][0];
        self::assertIsArray($firstResource['columns'] ?? null);
        self::assertArrayHasKey('weight', $firstResource['columns']);
        self::assertArrayHasKey('value', $firstResource['columns']);
        self::assertArrayHasKey('form_id', $firstResource['columns']);
        self::assertArrayHasKey('wiki_url', $firstResource['columns']);
        self::assertIsArray($firstResource['availability'] ?? null);
        self::assertArrayHasKey('containers', $firstResource['availability']);

        $availabilityValues = array_map(static function (mixed $row): bool {
            if (!is_array($row) || !is_array($row['availability'] ?? null)) {
                return false;
            }

            return (bool) ($row['availability']['containers'] ?? false);
        }, $pageDecoded['resources']);
        self::assertContains(true, $availabilityValues);
        self::assertContains(false, $availabilityValues);

        $nameTagsDetected = false;
        $currencyDetected = false;
        foreach ($pageDecoded['resources'] as $row) {
            if (!is_array($row) || !is_array($row['columns'] ?? null)) {
                continue;
            }
            $columns = $row['columns'];
            if (is_array($columns['name_tags'] ?? null) && in_array('Wastelanders', $columns['name_tags'], true)) {
                $nameTagsDetected = true;
            }
            if (is_scalar($columns['value_currency'] ?? null) && 'Bottle cap' === (string) $columns['value_currency']) {
                $currencyDetected = true;
            }
        }

        self::assertTrue($nameTagsDetected);
        self::assertTrue($currencyDetected);
    }

    public function testSyncFailsOnInvalidPayload(): void
    {
        $projectDir = sys_get_temp_dir().'/f76-sync-fandom-'.bin2hex(random_bytes(5));
        mkdir($projectDir, 0777, true);

        $client = new MockHttpClient([
            new MockResponse('{"parse":{"sections":[{"index":"1","line":"Recipes"}]}}'),
            new MockResponse('{"parse":{"oops":"missing-text"}}'),
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getProjectDir')
            ->willReturn($projectDir);

        $command = new SyncFandomDataCommand($kernel, $client);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--page' => ['Fallout_76_recipes'],
            '--no-delay' => true,
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('missing section html', strtolower($tester->getDisplay()));
    }
}
