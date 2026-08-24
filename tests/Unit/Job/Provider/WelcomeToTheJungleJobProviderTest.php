<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\WelcomeToTheJungleJobPostingParser;
use App\Job\Provider\WelcomeToTheJungleJobProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WelcomeToTheJungleJobProviderTest extends TestCase
{
    #[DataProvider('unavailableResponses')]
    public function testItOnlyExpiresAnOfferWhenTheRemotePageConfirmsIt(int $statusCode, string $body, JobOfferAvailability $expected): void
    {
        $provider = new WelcomeToTheJungleJobProvider(
            new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])),
            new WelcomeToTheJungleJobPostingParser(),
            10,
        );

        self::assertSame($expected, $provider->checkAvailability($this->offer()));
    }

    /** @return iterable<string, array{int, string, JobOfferAvailability}> */
    public static function unavailableResponses(): iterable
    {
        yield 'not found' => [404, '', JobOfferAvailability::EXPIRED];
        yield 'gone' => [410, '', JobOfferAvailability::EXPIRED];
        yield 'closed' => [200, '<div>Cette offre n\'est plus disponible</div>', JobOfferAvailability::EXPIRED];
        yield 'temporary server error' => [503, '', JobOfferAvailability::UNKNOWN];
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'WTTJ', 'https://www.welcometothejungle.com/fr/jobs?query=PHP', JobProviderType::WELCOME_TO_THE_JUNGLE),
            new NormalizedJobOffer(
                externalId: 'payfit_backend-engineer',
                url: 'https://www.welcometothejungle.com/fr/companies/payfit/jobs/backend-engineer',
                title: 'Backend Engineer',
                company: 'PayFit',
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
