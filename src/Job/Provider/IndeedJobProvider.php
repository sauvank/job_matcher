<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class IndeedJobProvider implements JobProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private IndeedJobPostingParser $parser,
        private int $maxOffers,
    ) {
    }

    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::INDEED;
    }

    public function fetch(JobSource $source): iterable
    {
        $listingContent = $this->fetchContent($source->getUrl());
        $offerUrls = $this->parser->extractOfferUrls($listingContent, $this->maxOffers);

        foreach ($offerUrls as $index => $url) {
            if ($index > 0) {
                usleep(250_000);
            }

            try {
                $offerHtml = $this->fetchContent($url);
                yield $this->parser->parseOffer($offerHtml, $url);
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
            if (preg_match('/Cette offre d[\'’]emploi a expiré|Cette offre n[\'’]est plus disponible|Job has expired/iu', $content) === 1) {
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
