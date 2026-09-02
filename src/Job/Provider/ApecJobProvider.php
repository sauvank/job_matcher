<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApecJobProvider implements JobProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private ApecJobPostingParser $parser,
        private int $maxOffers,
    ) {
    }

    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::APEC;
    }

    public function fetch(JobSource $source): iterable
    {
        $url = $source->getUrl();
        $queryParts = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $queryParts);
        $motsCles = isset($queryParts['motsCles']) && is_string($queryParts['motsCles']) ? trim($queryParts['motsCles']) : '';
        $lieux = isset($queryParts['lieux']) && is_string($queryParts['lieux']) ? trim($queryParts['lieux']) : '';
        $searchKeyword = trim(sprintf('%s %s', $motsCles, $lieux));

        if ($searchKeyword !== '') {
            try {
                $jsonPayload = [
                    'motsCles' => $motsCles !== '' ? $motsCles : $searchKeyword,
                    'pagination' => ['range' => $this->maxOffers, 'startIndex' => 0],
                    'activeFiltre' => false,
                    'typeClient' => 'CADRE',
                ];
                if ($lieux !== '') {
                    $jsonPayload['lieux'] = [$lieux];
                }

                $response = $this->httpClient->request('POST', 'https://www.apec.fr/cms/webservices/rechercheOffre', [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $jsonPayload,
                    'timeout' => 15,
                ]);

                if ($response->getStatusCode() === 200) {
                    $payload = $response->toArray(false);
                    $results = $payload['resultats'] ?? [];
                    if (is_array($results) && $results !== []) {
                        foreach ($results as $item) {
                            if (is_array($item)) {
                                yield $this->parser->parseOfferFromApi($item);
                            }
                        }

                        return;
                    }
                }
            } catch (\Throwable) {
                // Fallback to web scraping
            }
        }

        $listingContent = $this->fetchContent($source->getUrl());
        $offerUrls = $this->parser->extractOfferUrls($listingContent, $this->maxOffers);

        foreach ($offerUrls as $index => $offerUrl) {
            if ($index > 0) {
                usleep(250_000);
            }

            try {
                $offerHtml = $this->fetchContent($offerUrl);
                yield $this->parser->parseOffer($offerHtml, $offerUrl);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public function checkAvailability(JobOffer $offer): JobOfferAvailability
    {
        try {
            $response = $this->httpClient->request('GET', $offer->getUrl(), [
                'headers' => $this->headers(),
                'timeout' => 20,
                'max_redirects' => 3,
            ]);
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, [404, 410], true)) {
                return JobOfferAvailability::EXPIRED;
            }

            if ($statusCode !== 200) {
                return JobOfferAvailability::UNKNOWN;
            }

            $content = $response->getContent();
            if (preg_match('/Cette offre n[\'’]est plus disponible|Offre archivée|Offre expirée/iu', $content) === 1) {
                return JobOfferAvailability::EXPIRED;
            }

            $normalizedOffer = $this->parser->parseOffer($content, $offer->getUrl());

            return $normalizedOffer->validThrough !== null && $normalizedOffer->validThrough < new \DateTimeImmutable('today')
                ? JobOfferAvailability::EXPIRED
                : JobOfferAvailability::AVAILABLE;
        } catch (\Throwable) {
            return JobOfferAvailability::UNKNOWN;
        }
    }

    private function fetchContent(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => $this->headers(),
            'timeout' => 20,
            'max_redirects' => 5,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        return $response->getContent();
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'fr-FR,fr;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 JobMatcher/1.0',
        ];
    }
}
