<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Translation\JobMessage;

final class HelloWorkSearchUrlBuilder
{
    private const BASE_URL = 'https://www.hellowork.com/fr-fr/emploi/recherche.html';

    public function build(string $title, string $location): string
    {
        $title = trim($title);
        $location = trim($location);

        if ($title === '' || $location === '') {
            throw new \InvalidArgumentException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        return self::BASE_URL.'?'.http_build_query([
            'k' => $title,
            'k_autocomplete' => '',
            'l' => $location,
            'l_autocomplete' => '',
            'st' => 'relevance',
            'msa' => 0,
            'd' => 'all',
        ]);
    }
}
