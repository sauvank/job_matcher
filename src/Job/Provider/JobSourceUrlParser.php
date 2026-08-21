<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;

final class JobSourceUrlParser
{
    public function detect(string $url): JobProviderType
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            throw new \InvalidArgumentException(JobMessage::INVALID_URL);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($host, ['hellowork.com', 'www.hellowork.com'], true)) {
            throw new \InvalidArgumentException(JobMessage::UNSUPPORTED_PROVIDER);
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path !== '/fr-fr/emploi/recherche.html') {
            throw new \InvalidArgumentException(JobMessage::INVALID_URL);
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (!isset($query['k']) || !is_string($query['k']) || trim($query['k']) === '') {
            throw new \InvalidArgumentException(JobMessage::INVALID_URL);
        }

        return JobProviderType::HELLOWORK;
    }
}
