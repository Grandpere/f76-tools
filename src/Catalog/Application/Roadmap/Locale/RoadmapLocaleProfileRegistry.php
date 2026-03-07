<?php

declare(strict_types=1);

namespace App\Catalog\Application\Roadmap\Locale;

final class RoadmapLocaleProfileRegistry
{
    /**
     * @var list<RoadmapLocaleProfile>
     */
    private array $profiles;

    public function __construct(?array $profiles = null)
    {
        $this->profiles = is_array($profiles) && [] !== $profiles
            ? array_values(array_filter($profiles, static fn (mixed $profile): bool => $profile instanceof RoadmapLocaleProfile))
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

