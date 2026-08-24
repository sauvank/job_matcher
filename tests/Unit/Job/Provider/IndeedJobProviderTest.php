<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\IndeedJobPostingParser;
use App\Job\Provider\IndeedJobProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class IndeedJobProviderTest extends TestCase
{
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
