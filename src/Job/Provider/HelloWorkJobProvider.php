<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HelloWorkJobProvider implements JobProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private HelloWorkJobPostingParser $parser,
        private int $maxOffers,
    ) {
    }

    public function supports(JobProviderType $provider): bool
    {
        return $provider === JobProviderType::HELLOWORK;
    }

    public function fetch(JobSource $source): iterable
    {
        $listingHtml = $this->fetchHtml($source->getUrl());
        $offerUrls = $this->parser->extractOfferUrls($listingHtml, $this->maxOffers);

        foreach ($offerUrls as $index => $url) {
            if ($index > 0) {
                usleep(250_000);
            }

            yield $this->parser->parseOffer($this->fetchHtml($url), $url);
        }
    }

    private function fetchHtml(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'fr-FR,fr;q=0.9',
                'User-Agent' => 'JobMatcher/0.1 (+personal-use)',
            ],
            'timeout' => 20,
            'max_redirects' => 3,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(JobMessage::INVALID_RESPONSE);
        }

        return $response->getContent();
    }
}
