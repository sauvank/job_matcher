<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class FreeWorkJobProvider implements JobProviderInterface
{
    private const API_ENDPOINT = 'https://api.free-work.com/job_postings';

    public function __construct(
        private HttpClientInterface $httpClient,
        private FreeWorkJobPostingParser $parser,
        private int $maxOffers,
    ) {
    }

    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::FREE_WORK;
    }

    public function fetch(JobSource $source): iterable
    {
        [$title, $location, $contracts] = $this->extractCriteria($source->getUrl());
        if ($title === '' && $location === '') {
            throw new \RuntimeException(JobMessage::INVALID_URL);
        }

        $queryParams = [
            'itemsPerPage' => $this->maxOffers,
            'page' => 1,
        ];
        if ($title !== '') {
            $queryParams['query'] = $title;
        }
        if ($location !== '') {
            $queryParams['locations'] = $location;
        }
        if ($contracts !== '') {
            $queryParams['contracts'] = $contracts;
        }

        $response = $this->httpClient->request('GET', self::API_ENDPOINT, [
            'headers' => [
                'Accept' => 'application/ld+json, application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ],
            'query' => $queryParams,
            'timeout' => 20,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        $payload = $response->toArray(false);
        $members = $payload['hydra:member'] ?? $payload['member'] ?? null;
        if (!is_array($members)) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        foreach ($members as $item) {
            if (is_array($item)) {
                yield $this->parser->parseOffer($item);
            }
        }
    }

    public function checkAvailability(JobOffer $offer): JobOfferAvailability
    {
        try {
            $response = $this->httpClient->request('GET', $offer->getUrl(), [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                ],
                'timeout' => 15,
                'max_redirects' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            if (in_array($statusCode, [404, 410], true)) {
                return JobOfferAvailability::EXPIRED;
            }

            if ($statusCode !== 200) {
                return JobOfferAvailability::UNKNOWN;
            }

            return JobOfferAvailability::AVAILABLE;
        } catch (\Throwable) {
            return JobOfferAvailability::UNKNOWN;
        }
    }

    /** @return array{string, string, string} [title, location, contracts] */
    private function extractCriteria(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return ['', '', ''];
        }

        parse_str($query, $parameters);
        $title = isset($parameters['query']) && is_string($parameters['query']) ? trim($parameters['query']) : '';
        $location = isset($parameters['locations']) && is_string($parameters['locations']) ? trim($parameters['locations']) : '';
        $contracts = isset($parameters['contracts']) && is_string($parameters['contracts']) ? trim($parameters['contracts']) : '';

        return [$title, $location, $contracts];
    }
}
