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

namespace App\Catalog\Infrastructure\Translation;

use App\Catalog\Application\Translation\TranslationCatalogWriter as TranslationCatalogWriterPort;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

final class TranslationCatalogWriter implements TranslationCatalogWriterPort
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly KernelInterface $kernel,
        ?Filesystem $filesystem = null,
    ) {
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    /**
     * @param array<string, string> $entries
     */
    public function upsert(string $locale, string $domain, array $entries): void
    {
        if ([] === $entries) {
            return;
        }

        $translationDir = rtrim($this->kernel->getProjectDir(), '/').'/translations';
        $this->filesystem->mkdir($translationDir);

        $file = sprintf('%s/%s.%s.yaml', $translationDir, $domain, $locale);
        $current = $this->loadCatalog($file);

        foreach ($entries as $key => $value) {
            $normalizedKey = trim($key);
            if ('' === $normalizedKey) {
                continue;
            }

            $current[$normalizedKey] = $value;
        }

        $yaml = $this->renderStructuredYaml($current);

        $tmpFile = $file.'.tmp';
        $this->filesystem->dumpFile($tmpFile, $yaml);
        $this->filesystem->rename($tmpFile, $file, true);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalog(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $data = Yaml::parseFile($file);
        if (!is_array($data)) {
            return [];
        }

        $flat = [];
        foreach ($data as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            $flat[$key] = (string) $value;
        }

        return $flat;
    }

    /**
     * @param array<string, string> $entries
     */
    private function renderStructuredYaml(array $entries): string
    {
        $misc = [];
        $book = [];
        $other = [];

        foreach ($entries as $key => $value) {
            if (str_starts_with($key, 'item.misc.')) {
                $misc[$key] = $value;
                continue;
            }

            if (str_starts_with($key, 'item.book.')) {
                $book[$key] = $value;
                continue;
            }

            $other[$key] = $value;
        }

        ksort($misc);
        ksort($book);
        ksort($other);

        $chunks = [];

        if ([] !== $misc) {
            $chunks[] = '# misc (legendary mod)'."\n".$this->dumpFlatMap($misc);
        }

        if ([] !== $book) {
            $chunks[] = '# book (minerva plan)'."\n".$this->dumpFlatMap($book);
        }

        if ([] !== $other) {
            $chunks[] = '# other'."\n".$this->dumpFlatMap($other);
        }

        if ([] === $chunks) {
            return '';
        }

        return implode("\n\n", $chunks)."\n";
    }

    /**
     * @param array<string, string> $entries
     */
    private function dumpFlatMap(array $entries): string
    {
        $yaml = Yaml::dump($entries, 2, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        return rtrim($yaml, "\n");
    }
}
