<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Translation\JobMessage;

final class ApecSearchUrlBuilder
{
    private const BASE_URL = 'https://www.apec.fr/candidat/recherche-emploi.html/emploi';

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
        ]);
    }
}
