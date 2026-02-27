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

use App\Catalog\Application\Translation\TranslationCatalogReader as TranslationCatalogReaderPort;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

final class TranslationCatalogReader implements TranslationCatalogReaderPort
{
    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function load(string $locale, string $domain): array
    {
        $translationDir = rtrim($this->kernel->getProjectDir(), '/').'/translations';
        $file = sprintf('%s/%s.%s.yaml', $translationDir, $domain, $locale);
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
}
