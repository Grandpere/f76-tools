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

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Import\ItemImportSourceReader;
use JsonException;
use Symfony\Component\Finder\Finder;

final class FilesystemItemImportSourceReader implements ItemImportSourceReader
{
    /**
     * @return list<string>
     */
    public function findImportFiles(string $rootPath): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($rootPath)
            ->name('*.json')
            ->notName('manifest.json')
            ->notName('index.json');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath() ?: $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<mixed>|null
     */
    public function readRows(string $path): ?array
    {
        try {
            $json = file_get_contents($path);
            if (false === $json) {
                return null;
            }

            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return null;
            }

            if (array_is_list($decoded)) {
                return $decoded;
            }

            $resources = $decoded['resources'] ?? null;
            if (!is_array($resources) || !array_is_list($resources)) {
                return null;
            }

            $rows = [];
            /** @var array<string, mixed> $payload */
            $payload = $decoded;
            foreach ($resources as $resource) {
                if (!is_array($resource)) {
                    continue;
                }

                /** @var array<string, mixed> $resourceRow */
                $resourceRow = $resource;
                $rows[] = $this->normalizeResourceRow($payload, $resourceRow);
            }

            return $rows;
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $resource
     *
     * @return array<string, mixed>
     */
    private function normalizeResourceRow(array $payload, array $resource): array
    {
        $row = [];

        $columns = $resource['columns'] ?? null;
        if (is_array($columns)) {
            foreach ($columns as $key => $value) {
                $row[(string) $key] = $value;
            }
        }

        $availability = $resource['availability'] ?? null;
        if (is_array($availability)) {
            foreach ($availability as $key => $value) {
                $row[(string) $key] = $value;
            }
        }

        $name = $resource['title'] ?? $resource['name'] ?? null;
        if (is_scalar($name) && '' !== trim((string) $name)) {
            $normalizedName = trim((string) $name);
            $rawName = $row['name'] ?? null;
            if (is_scalar($rawName) && '' !== trim((string) $rawName) && trim((string) $rawName) !== $normalizedName) {
                $row['source_name_raw'] = trim((string) $rawName);
            }

            $row['name'] = $normalizedName;
            $row['name_en'] = $normalizedName;
        }

        $type = $resource['type'] ?? null;
        if (is_scalar($type)) {
            $row['source_item_type'] = (string) $type;
        }

        $slug = $resource['slug'] ?? null;
        if (is_scalar($slug)) {
            $row['source_slug'] = (string) $slug;
        }

        $section = $resource['section'] ?? null;
        if (is_scalar($section)) {
            $row['source_section'] = (string) $section;
        }

        $page = $payload['page'] ?? null;
        if (is_scalar($page)) {
            $row['source_page'] = (string) $page;
        }

        $url = $payload['url'] ?? null;
        if (is_scalar($url)) {
            $row['source_page_url'] = (string) $url;
        }

        $generatedAt = $payload['generated_at'] ?? null;
        if (is_scalar($generatedAt)) {
            $row['source_generated_at'] = (string) $generatedAt;
        }

        $formId = $row['form_id'] ?? null;
        if (is_scalar($formId)) {
            $normalizedFormId = strtoupper(trim((string) $formId));
            $row['form_id'] = $normalizedFormId;
            $row['id'] = $this->deriveIdFromFormId($normalizedFormId);
        }

        return $row;
    }

    private function deriveIdFromFormId(string $formId): ?int
    {
        if (!preg_match('/^[0-9A-F]{1,8}$/', $formId)) {
            return null;
        }

        $value = (int) hexdec($formId);

        return $value > 0 ? $value : null;
    }
}
