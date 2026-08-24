<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BrowserScraperClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $browserDsn = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->browserDsn !== null && trim($this->browserDsn) !== '';
    }

    public function scrape(string $url, int $timeoutSeconds = 25): string
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Browser scraping service is not configured');
        }

        $endpoint = rtrim((string) $this->browserDsn, '/').'/scrape';

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'url' => $url,
                'timeout' => $timeoutSeconds * 1000,
            ],
            'timeout' => $timeoutSeconds + 5,
        ]);

        $statusCode = $response->getStatusCode();
        /** @var array{html?: string, error?: string, status?: int, finalUrl?: string} $data */
        $data = $response->toArray(false);

        if ($statusCode >= 400 || isset($data['error'])) {
            $errorMessage = $data['error'] ?? sprintf('Browser scraping service failed with HTTP %d', $statusCode);
            throw new \RuntimeException((string) $errorMessage);
        }

        $html = $data['html'] ?? null;
        if (!is_string($html) || trim($html) === '') {
            throw new \RuntimeException('Browser scraping service returned empty HTML');
        }

        $upstreamStatus = $data['status'] ?? null;
        if ($upstreamStatus === 403 || $upstreamStatus === 429 || $this->isIndeedSecurityPage($html)) {
            throw new \RuntimeException(JobMessage::INDEED_BLOCKED);
        }
        if (is_int($upstreamStatus) && $upstreamStatus >= 400) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        return $html;
    }

    private function isIndeedSecurityPage(string $html): bool
    {
        return preg_match('/<title[^>]*>\s*Security Check\s*-\s*Indeed\.com\s*<\/title>/iu', $html) === 1
            || stripos($html, 'Additional Verification Required') !== false;
    }
}
