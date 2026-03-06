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

namespace App\Catalog\Application\Roadmap\Ocr;

use RuntimeException;
use Throwable;

final class OcrProviderChain
{
    /** @var list<OcrProvider> */
    private array $providers;

    /**
     * @param iterable<OcrProvider> $providers
     */
    public function __construct(
        iterable $providers,
        private readonly OcrQualityAssessor $qualityAssessor = new OcrQualityAssessor(),
    ) {
        $this->providers = is_array($providers) ? array_values($providers) : array_values(iterator_to_array($providers));
    }

    public function recognize(string $imagePath, string $locale): OcrChainResult
    {
        if ([] === $this->providers) {
            throw new RuntimeException('No OCR provider configured.');
        }

        $attempts = [];
        $lastSuccessfulResult = null;

        foreach ($this->providers as $provider) {
            try {
                $result = $provider->recognize($imagePath, $locale);
                $assessment = $this->qualityAssessor->assess($result);

                $attempts[] = new OcrAttempt(
                    $provider->name(),
                    true,
                    $result->confidence,
                    $assessment->acceptable,
                    $assessment->reasons,
                );

                $lastSuccessfulResult = $result;
                if ($assessment->acceptable) {
                    return new OcrChainResult(
                        $result,
                        count($attempts) > 1,
                        $attempts,
                    );
                }
            } catch (Throwable $exception) {
                $attempts[] = new OcrAttempt(
                    $provider->name(),
                    false,
                    null,
                    false,
                    [],
                    $exception->getMessage(),
                );
            }
        }

        if ($lastSuccessfulResult instanceof OcrResult) {
            return new OcrChainResult(
                $lastSuccessfulResult,
                count($attempts) > 1,
                $attempts,
            );
        }

        $parts = [];
        foreach ($attempts as $attempt) {
            $fragment = $attempt->provider.': '.($attempt->error ?? 'no output');
            $parts[] = $fragment;
        }

        throw new RuntimeException('All OCR providers failed without producing any result. '.implode(' | ', $parts));
    }
}
