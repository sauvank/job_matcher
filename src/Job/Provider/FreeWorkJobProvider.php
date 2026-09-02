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
            if (!is_array($item)) {
                continue;
            }

            $offer = $this->parser->parseOffer($item);
            if ($this->matchesSearchArea($item, $offer->remotePolicy, $location)) {
                yield $offer;
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

    /** @param array<string, mixed> $item */
    private function matchesSearchArea(array $item, ?string $remotePolicy, string $searchLocation): bool
    {
        $location = $item['location'] ?? null;
        if (!is_array($location)) {
            return false;
        }

        $expectedCountry = $this->expectedCountryCode($searchLocation);
        $countryCode = $location['countryCode'] ?? null;
        if (!is_string($countryCode) || mb_strtoupper(trim($countryCode)) !== $expectedCountry) {
            return false;
        }

        if ($remotePolicy === 'REMOTE' || $this->isCountryWideSearch($searchLocation)) {
            return true;
        }

        $area = $this->normalize($searchLocation);
        $ignoredWords = ['et', 'peripherie', 'alentours', 'region', 'metropole', 'france', 'suisse', 'belgique', 'luxembourg'];
        $searchWords = array_values(array_filter(
            explode(' ', $area),
            static fn (string $word): bool => mb_strlen($word) >= 3 && !in_array($word, $ignoredWords, true),
        ));
        if ($searchWords === []) {
            return false;
        }

        $locationText = $this->normalize(implode(' ', array_filter(
            $location,
            static fn (mixed $value): bool => is_string($value),
        )));

        return array_any($searchWords, static fn (string $word): bool => str_contains($locationText, $word));
    }

    private function expectedCountryCode(string $searchLocation): string
    {
        $location = $this->normalize($searchLocation);

        return match (true) {
            str_contains($location, 'suisse'), str_contains($location, 'switzerland') => 'CH',
            str_contains($location, 'belgique'), str_contains($location, 'belgium') => 'BE',
            str_contains($location, 'luxembourg') => 'LU',
            default => 'FR',
        };
    }

    private function isCountryWideSearch(string $searchLocation): bool
    {
        $location = $this->normalize($searchLocation);

        return str_contains($location, 'entiere')
            || in_array($location, ['france', 'suisse', 'switzerland', 'belgique', 'belgium', 'luxembourg'], true);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
