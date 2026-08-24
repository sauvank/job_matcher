<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Translation\JobMessage;

final class FranceTravailSearchUrlBuilder
{
    private const BASE_URL = 'https://candidat.francetravail.fr/offres/recherche';

    public function build(string $title, string $location): string
    {
        $title = trim($title);
        $location = trim($location);

        if ($title === '' || $location === '') {
            throw new \InvalidArgumentException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        return self::BASE_URL.'?'.http_build_query([
            'motsCles' => $title,
            'lieux' => $location,
            'offresPartenaires' => 'true',
            'rayon' => '10',
            'tri' => '0',
        ]);
    }
}
