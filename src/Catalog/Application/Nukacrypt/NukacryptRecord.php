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

namespace App\Catalog\Application\Nukacrypt;

final readonly class NukacryptRecord
{
    /**
     * @param array<string, mixed>|null $recordData
     */
    public function __construct(
        public string $formId,
        public ?string $name,
        public ?string $editorId,
        public ?string $signature,
        public ?string $description,
        public ?string $esmFileName,
        public ?string $updatedAt,
        public bool $hasErrors,
        public ?array $recordData,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'form_id' => $this->formId,
            'name' => $this->name,
            'editor_id' => $this->editorId,
            'signature' => $this->signature,
            'description' => $this->description,
            'esm_file_name' => $this->esmFileName,
            'updated_at' => $this->updatedAt,
            'has_errors' => $this->hasErrors,
            'record_data' => $this->recordData,
        ];
    }
}
