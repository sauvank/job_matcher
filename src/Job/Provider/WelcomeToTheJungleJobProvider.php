<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class WelcomeToTheJungleJobProvider implements JobProviderInterface
{
    private const SEARCH_ENDPOINT = 'https://csekhvms53-dsn.algolia.net/1/indexes/*/queries?x-algolia-agent=Algolia%20for%20JavaScript%20(4.20.0)%3B%20Browser&search_origin=jobs_search_client';
    private const APPLICATION_ID = 'CSEKHVMS53';
    private const PUBLIC_SEARCH_KEY = '4bd8f6215d0cc52b26430765769e65a0';

    public function __construct(
        private HttpClientInterface $httpClient,
        private WelcomeToTheJungleJobPostingParser $parser,
        private int $maxOffers,
    ) {
    }

    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::WELCOME_TO_THE_JUNGLE;
    }

    public function fetch(JobSource $source): iterable
    {
        [$title, $location] = $this->extractCriteria($source->getUrl());
        $query = trim($title.' '.$location);
        if ($query === '') {
            throw new \RuntimeException(JobMessage::INVALID_URL);
        }

        $params = http_build_query([
            'query' => $query,
            'hitsPerPage' => $this->maxOffers,
            'page' => 0,
            'filters' => 'website.reference:wttj_fr',
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->httpClient->request('POST', self::SEARCH_ENDPOINT, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Origin' => 'https://www.welcometothejungle.com',
                'Referer' => 'https://www.welcometothejungle.com/',
                'X-Algolia-Application-Id' => self::APPLICATION_ID,
                'X-Algolia-Api-Key' => self::PUBLIC_SEARCH_KEY,
            ],
            'body' => json_encode([
                'requests' => [[
                    'indexName' => 'wk_cms_jobs_production',
                    'params' => $params,
                ]],
            ], JSON_THROW_ON_ERROR),
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        $payload = $response->toArray(false);
        $hits = $payload['results'][0]['hits'] ?? null;
        if (!is_array($hits)) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        foreach ($hits as $hit) {
            if (is_array($hit)) {
                yield $this->parser->parseOffer($hit);
            }
        }
    }

    public function checkAvailability(JobOffer $offer): JobOfferAvailability
    {
        return JobOfferAvailability::UNKNOWN;
    }

    /** @return array{string, string} */
    private function extractCriteria(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return ['', ''];
        }

        parse_str($query, $parameters);
        $title = isset($parameters['query']) && is_string($parameters['query']) ? trim($parameters['query']) : '';
        $location = isset($parameters['aroundQuery']) && is_string($parameters['aroundQuery']) ? trim($parameters['aroundQuery']) : '';

        return [$title, $location];
    }
}
