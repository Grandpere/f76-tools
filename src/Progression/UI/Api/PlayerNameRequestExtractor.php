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

namespace App\Progression\UI\Api;

use Symfony\Component\HttpFoundation\Request;

final class PlayerNameRequestExtractor
{
    public function __construct(
        private readonly ProgressionApiJsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function extract(Request $request): ?string
    {
        $payload = $this->jsonPayloadDecoder->decode($request);
        $value = $payload['name'] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
