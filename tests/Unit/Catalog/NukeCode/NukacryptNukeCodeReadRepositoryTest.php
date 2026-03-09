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

namespace App\Tests\Unit\Catalog\NukeCode;

use App\Catalog\Infrastructure\NukeCode\NukacryptNukeCodeReadRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NukacryptNukeCodeReadRepositoryTest extends TestCase
{
    public function testFetchCurrentReturnsCodesFromGraphqlPayload(): void
    {
        $responsePayload = [
            'data' => [
                'nukeCodes' => [
                    'alpha' => '59586541',
                    'bravo' => '99725388',
                    'charlie' => '00763938',
                ],
            ],
        ];

        $client = new MockHttpClient([
            new MockResponse(json_encode($responsePayload, JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptNukeCodeReadRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $codes = $repository->fetchCurrent();

        self::assertSame('59586541', $codes['alpha']);
        self::assertSame('99725388', $codes['bravo']);
        self::assertSame('00763938', $codes['charlie']);
    }

    public function testFetchCurrentThrowsWhenPayloadMissingExpectedFields(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['data' => []], JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptNukeCodeReadRepository(
            $client,
            'https://api.nukacrypt.com/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $this->expectException(RuntimeException::class);

        $repository->fetchCurrent();
    }

    public function testFetchCurrentThrowsWhenGraphqlUrlIsNotAllowed(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['data' => []], JSON_THROW_ON_ERROR)),
        ]);

        $repository = new NukacryptNukeCodeReadRepository(
            $client,
            'http://evil.local/graphql',
            'f76-data-sync-experimentation/1.0',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must target https://api.nukacrypt.com');

        $repository->fetchCurrent();
    }
}
