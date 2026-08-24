<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\BrowserScraperClient;
use App\Job\Provider\IndeedJobPostingParser;
use App\Job\Provider\IndeedJobProvider;
use App\Job\Translation\JobMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IndeedJobProviderTest extends TestCase
{
    public function testItSupportsIndeedProviderOnly(): void
    {
        $provider = new IndeedJobProvider(new MockHttpClient(), new IndeedJobPostingParser(), 10);

        self::assertTrue($provider->supports(JobProviderType::INDEED));
        self::assertFalse($provider->supports(JobProviderType::HELLOWORK));
        self::assertFalse($provider->supports(JobProviderType::APEC));
        self::assertFalse($provider->supports(JobProviderType::FRANCE_TRAVAIL));
    }

    public function testItFetchesOffersUsingBrowserClientWhenAvailable(): void
    {
        $listingHtml = '<html><body><a data-jk="job12345678901234" href="/viewjob?jk=job12345678901234">Job</a></body></html>';
        $detailHtml = '<script type="application/ld+json">{"@context":"https://schema.org","@type":"JobPosting","title":"Dev PHP","hiringOrganization":{"name":"ACME"},"jobLocation":{"address":{"addressLocality":"Lyon"}}}</script>';

        $browserClient = $this->createMock(BrowserScraperClient::class);
        $browserClient->method('isAvailable')->willReturn(true);
        $browserClient->expects(self::exactly(2))
            ->method('scrape')
            ->willReturnMap([
                ['https://fr.indeed.com/emplois?q=PHP&l=Lyon', 25, $listingHtml],
                ['https://fr.indeed.com/viewjob?jk=job12345678901234', 25, $detailHtml],
            ]);

        $provider = new IndeedJobProvider(
            new MockHttpClient(),
            new IndeedJobPostingParser(),
            10,
            $browserClient,
        );

        $source = new JobSource(new CandidateProfile(), 'Indeed', 'https://fr.indeed.com/emplois?q=PHP&l=Lyon', JobProviderType::INDEED);
        $offers = iterator_to_array($provider->fetch($source));

        self::assertCount(1, $offers);
        self::assertSame('Dev PHP', $offers[0]->title);
        self::assertSame('ACME', $offers[0]->company);
        self::assertSame('Lyon', $offers[0]->location);
    }

    public function testItThrowsIndeedBlockedOn403WithoutBrowser(): void
    {
        $provider = new IndeedJobProvider(
            new MockHttpClient(new MockResponse('', ['http_code' => 403])),
            new IndeedJobPostingParser(),
            10,
        );

        $source = new JobSource(new CandidateProfile(), 'Indeed', 'https://fr.indeed.com/emplois?q=PHP', JobProviderType::INDEED);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(JobMessage::INDEED_BLOCKED);

        iterator_to_array($provider->fetch($source));
    }

    #[DataProvider('unavailableResponses')]
    public function testItOnlyExpiresAnOfferWhenTheRemotePageConfirmsIt(int $statusCode, string $body, JobOfferAvailability $expected): void
    {
        $provider = new IndeedJobProvider(
            new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])),
            new IndeedJobPostingParser(),
            10,
        );

        self::assertSame($expected, $provider->checkAvailability($this->offer()));
    }

    /** @return iterable<string, array{int, string, JobOfferAvailability}> */
    public static function unavailableResponses(): iterable
    {
        yield 'not found' => [404, '', JobOfferAvailability::EXPIRED];
        yield 'gone' => [410, '', JobOfferAvailability::EXPIRED];
        yield 'expired text' => [200, '<div>Cette offre d\'emploi a expiré</div>', JobOfferAvailability::EXPIRED];
        yield 'temporary server error' => [503, '', JobOfferAvailability::UNKNOWN];
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'Indeed', 'https://fr.indeed.com/emplois?q=PHP', JobProviderType::INDEED),
            new NormalizedJobOffer(
                externalId: 'abcdef1234567890',
                url: 'https://fr.indeed.com/viewjob?jk=abcdef1234567890',
                title: 'Développeur PHP',
                company: 'ACME',
                location: 'Paris',
                contractType: 'CDI',
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: null,
                yearsOfExperience: null,
                description: null,
                publishedAt: null,
                validThrough: null,
                rawPayload: [],
            ),
        );
    }
}
