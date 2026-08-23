<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\HelloWorkJobPostingParser;
use App\Job\Provider\HelloWorkJobProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HelloWorkJobProviderTest extends TestCase
{
    #[DataProvider('unavailableResponses')]
    public function testItOnlyExpiresAnOfferWhenTheRemotePageConfirmsIt(int $statusCode, JobOfferAvailability $expected): void
    {
        $provider = new HelloWorkJobProvider(
            new MockHttpClient(new MockResponse('', ['http_code' => $statusCode])),
            new HelloWorkJobPostingParser(),
            10,
        );

        self::assertSame($expected, $provider->checkAvailability($this->offer()));
    }

    /** @return iterable<string, array{int, JobOfferAvailability}> */
    public static function unavailableResponses(): iterable
    {
        yield 'not found' => [404, JobOfferAvailability::EXPIRED];
        yield 'gone' => [410, JobOfferAvailability::EXPIRED];
        yield 'temporary server error' => [503, JobOfferAvailability::UNKNOWN];
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'HelloWork', 'https://example.test/jobs', JobProviderType::HELLOWORK),
            new NormalizedJobOffer(
                externalId: '123',
                url: 'https://www.hellowork.com/fr-fr/emplois/123.html',
                title: 'Développeur PHP',
                company: null,
                location: null,
                contractType: null,
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
