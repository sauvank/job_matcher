<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\FranceTravailJobPostingParser;
use App\Job\Provider\FranceTravailJobProvider;
use App\Job\Translation\JobMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FranceTravailJobProviderTest extends TestCase
{
    public function testItFailsWhenListingsExistButNoDetailCanBeParsed(): void
    {
        $client = new MockHttpClient([
            new MockResponse('<a href="/offres/recherche/detail/212VBFN">Offre</a>'),
            new MockResponse('<html><body>Réponse sans annonce exploitable</body></html>'),
        ]);
        $provider = new FranceTravailJobProvider($client, new FranceTravailJobPostingParser(), 10);
        $source = new JobSource(
            new CandidateProfile(),
            'France Travail',
            'https://candidat.francetravail.fr/offres/recherche?motsCles=PHP+Lyon',
            JobProviderType::FRANCE_TRAVAIL,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(JobMessage::INVALID_RESPONSE);

        iterator_to_array($provider->fetch($source));
    }

    #[DataProvider('unavailableResponses')]
    public function testItOnlyExpiresAnOfferWhenTheRemotePageConfirmsIt(int $statusCode, string $body, JobOfferAvailability $expected): void
    {
        $provider = new FranceTravailJobProvider(
            new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode])),
            new FranceTravailJobPostingParser(),
            10,
        );

        self::assertSame($expected, $provider->checkAvailability($this->offer()));
    }

    /** @return iterable<string, array{int, string, JobOfferAvailability}> */
    public static function unavailableResponses(): iterable
    {
        yield 'not found' => [404, '', JobOfferAvailability::EXPIRED];
        yield 'gone' => [410, '', JobOfferAvailability::EXPIRED];
        yield 'cancelled' => [200, '<div>Cette offre n\'est plus disponible</div>', JobOfferAvailability::EXPIRED];
        yield 'temporary server error' => [503, '', JobOfferAvailability::UNKNOWN];
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'France Travail', 'https://candidat.francetravail.fr/offres/recherche?motsCles=PHP', JobProviderType::FRANCE_TRAVAIL),
            new NormalizedJobOffer(
                externalId: '184ABCD',
                url: 'https://candidat.francetravail.fr/offres/recherche/detail/184ABCD',
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
