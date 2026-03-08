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

namespace App\Catalog\Application\Roadmap\Locale;

final class RoadmapLocaleProfileRegistry
{
    /**
     * @var list<RoadmapLocaleProfile>
     */
    private array $profiles;

    /**
     * @param list<RoadmapLocaleProfile>|null $profiles
     */
    public function __construct(?array $profiles = null)
    {
        $this->profiles = is_array($profiles) && [] !== $profiles
            ? $profiles
            : [
                new FrenchRoadmapLocaleProfile(),
                new GermanRoadmapLocaleProfile(),
                new EnglishRoadmapLocaleProfile(),
            ];
    }

    public function profileFor(string $locale): RoadmapLocaleProfile
    {
        $normalizedLocale = strtolower(trim($locale));
        foreach ($this->profiles as $profile) {
            if ($profile->supports($normalizedLocale)) {
                return $profile;
            }
        }

        return new EnglishRoadmapLocaleProfile();
    }
}
