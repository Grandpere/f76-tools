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

use App\Catalog\Application\Roadmap\RoadmapSeasonExtractor;
use App\Catalog\Application\Roadmap\RoadmapSeasonRepository;
use App\Catalog\Application\Roadmap\RoadmapSnapshotWriteRepository;
use App\Catalog\Domain\Entity\RoadmapSeasonEntity;
use App\Catalog\Domain\Entity\RoadmapSnapshotEntity;
use App\Catalog\Domain\Roadmap\RoadmapOcrProcessingStatusEnum;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessRoadmapSnapshotOcrMessageHandler
{
    public function __construct(
        private RoadmapSnapshotWriteRepository $roadmapSnapshotWriteRepository,
        private OcrProviderChain $ocrProviderChain,
        private GdImagePreprocessor $gdImagePreprocessor,
        private RoadmapSeasonExtractor $roadmapSeasonExtractor,
        private RoadmapSeasonRepository $roadmapSeasonRepository,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(ProcessRoadmapSnapshotOcrMessage $message): void
    {
        $snapshot = $this->roadmapSnapshotWriteRepository->findOneById($message->snapshotId);
        if (!$snapshot instanceof RoadmapSnapshotEntity) {
            return;
        }

        $absoluteImagePath = $this->resolveAbsoluteImagePath($snapshot);
        if (null === $absoluteImagePath || !is_file($absoluteImagePath)) {
            $this->markFailed($snapshot, 'Snapshot source image not found.');

            return;
        }

        $snapshot
            ->setOcrProcessingStatus(RoadmapOcrProcessingStatusEnum::PROCESSING)
            ->setOcrProcessingError(null)
            ->setOcrPreprocessMode($message->preprocessMode);
        $this->roadmapSnapshotWriteRepository->save($snapshot);

        $prepared = ['path' => $absoluteImagePath, 'temporary' => false];

        try {
            $prepared = $this->gdImagePreprocessor->prepare($absoluteImagePath, $message->preprocessMode);
            $scan = $this->ocrProviderChain->recognize($prepared['path'], $message->locale);
            $rawText = $this->buildStructuredRawText($scan->result->lines, $scan->result->text);

            $snapshot
                ->setOcrProvider($scan->result->provider)
                ->setOcrConfidence($scan->result->confidence)
                ->setRawText($rawText)
                ->setOcrAttemptsSummary($this->buildOcrAttemptsSummary($scan->attempts))
                ->setScannedAt(new DateTimeImmutable())
                ->setOcrProcessingStatus(RoadmapOcrProcessingStatusEnum::DONE)
                ->setOcrProcessingError(null);

            $seasonNumber = $this->roadmapSeasonExtractor->extractSeasonNumber($rawText);
            if (is_int($seasonNumber) && $seasonNumber > 0) {
                $season = $this->roadmapSeasonRepository->findOneBySeasonNumber($seasonNumber);
                if (!$season instanceof RoadmapSeasonEntity) {
                    $season = new RoadmapSeasonEntity()
                        ->setSeasonNumber($seasonNumber)
                        ->setTitle(sprintf('Season %d', $seasonNumber));
                    $this->roadmapSeasonRepository->save($season);
                }
                $snapshot->setSeason($season);
            }

            $this->roadmapSnapshotWriteRepository->save($snapshot);
        } catch (Throwable $exception) {
            $this->markFailed($snapshot, $exception->getMessage());
            throw $exception;
        } finally {
            $this->gdImagePreprocessor->cleanup($prepared['path'], true === $prepared['temporary']);
        }
    }

    private function markFailed(RoadmapSnapshotEntity $snapshot, string $error): void
    {
        $snapshot
            ->setOcrProcessingStatus(RoadmapOcrProcessingStatusEnum::FAILED)
            ->setOcrProcessingError($error);
        $this->roadmapSnapshotWriteRepository->save($snapshot);
    }

    private function resolveAbsoluteImagePath(RoadmapSnapshotEntity $snapshot): ?string
    {
        $path = trim($snapshot->getSourceImagePath());
        if ('' === $path) {
            return null;
        }
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return rtrim($this->projectDir, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param list<string> $lines
     */
    private function buildStructuredRawText(array $lines, string $fallbackText): string
    {
        $normalizedLines = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            $lines,
        ), static fn (string $line): bool => '' !== $line));

        if ([] !== $normalizedLines) {
            return implode("\n", $normalizedLines);
        }

        return $fallbackText;
    }

    /**
     * @param list<OcrAttempt> $attempts
     */
    private function buildOcrAttemptsSummary(array $attempts): string
    {
        if ([] === $attempts) {
            return '';
        }

        $lines = [];
        foreach ($attempts as $attempt) {
            $line = sprintf(
                '%s | success=%s | acceptable=%s',
                $attempt->provider,
                $attempt->successful ? 'yes' : 'no',
                $attempt->acceptable ? 'yes' : 'no',
            );
            if (null !== $attempt->confidence) {
                $line .= sprintf(' | confidence=%.4f', $attempt->confidence);
            }
            if ([] !== $attempt->qualityReasons) {
                $line .= ' | reasons='.implode('; ', $attempt->qualityReasons);
            }
            if (null !== $attempt->error) {
                $line .= ' | error='.$attempt->error;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
