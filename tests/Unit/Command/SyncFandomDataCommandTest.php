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
    public function testSyncWritesExtractedResourcesToOutputFile(): void
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
<div>
  <a href="/wiki/Recipe:Berry_Mentats">Recipe: Berry Mentats</a>
  <a href="/wiki/Plan:Laser_Gun">Plan: Laser Gun</a>
</div>
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
            '--output' => 'data/assets/fandom/test_output.json',
            '--no-delay' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $outputPath = $projectDir.'/data/assets/fandom/test_output.json';
        self::assertFileExists($outputPath);

        $decoded = json_decode((string) file_get_contents($outputPath), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('pages', $decoded);
        self::assertArrayHasKey('resources', $decoded);
        self::assertIsArray($decoded['pages']);
        self::assertIsArray($decoded['resources']);
        self::assertSame(2, $decoded['resources_total']);
        self::assertCount(1, $decoded['pages']);
        self::assertIsArray($decoded['pages'][0]);
        self::assertSame('Fallout_76_recipes', $decoded['pages'][0]['page']);
        self::assertIsArray($decoded['resources'][0]);
        $types = array_map(static function (mixed $row): string {
            if (!is_array($row)) {
                return '';
            }

            $type = $row['type'] ?? '';

            return is_scalar($type) ? (string) $type : '';
        }, $decoded['resources']);
        sort($types);
        self::assertSame(['plan', 'recipe'], $types);
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
