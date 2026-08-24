<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\ApecJobPostingParser;
use App\Job\Provider\ApecJobProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ApecJobProviderTest extends TestCase
{
    #[DataProvider('unavailableResponses')]
    public function testItOnlyExpiresAnOfferWhenTheRemotePageConfirmsIt(int $statusCode, string $body, JobOfferAvailability $expected): void
    {
        $provider = new ApecJobProvider(
            new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])),
            new ApecJobPostingParser(),
            10,
        );

        self::assertSame($expected, $provider->checkAvailability($this->offer()));
    }

    /** @return iterable<string, array{int, string, JobOfferAvailability}> */
    public static function unavailableResponses(): iterable
    {
        yield 'not found' => [404, '', JobOfferAvailability::EXPIRED];
        yield 'gone' => [410, '', JobOfferAvailability::EXPIRED];
        yield 'archived' => [200, '<div>Cette offre n\'est plus disponible</div>', JobOfferAvailability::EXPIRED];
        yield 'temporary server error' => [503, '', JobOfferAvailability::UNKNOWN];
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'Apec', 'https://www.apec.fr/candidat/recherche-emploi.html/emploi?motsCles=PHP', JobProviderType::APEC),
            new NormalizedJobOffer(
                externalId: '176000100W',
                url: 'https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/176000100W',
                title: 'Développeur PHP',
                company: 'ACME',
                location: 'Lyon',
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
