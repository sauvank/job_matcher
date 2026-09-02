<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\FreeWorkJobPostingParser;
use App\Job\Provider\FreeWorkJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FreeWorkJobProviderTest extends TestCase
{
    public function testItImportsStructuredOffersFromFreeWorkApi(): void
    {
        $payload = [
            'hydra:member' => [
                [
                    'id' => 639398,
                    'title' => 'Développeur Symfony Freelance',
                    'slug' => 'developpeur-symfony-freelance',
                    'description' => '<p>Mission de 6 mois renouvelable pour développer une API REST avec Symfony.</p>',
                    'publishedAt' => '2026-09-02T10:00:00+02:00',
                    'expiredAt' => '2026-11-01T10:00:00+01:00',
                    'company' => ['name' => 'Tech Mission', 'slug' => 'tech-mission'],
                    'contracts' => ['contractor'],
                    'experienceLevel' => 'senior',
                    'minDailySalary' => 550,
                    'maxDailySalary' => 650,
                    'minAnnualSalary' => null,
                    'maxAnnualSalary' => null,
                    'remoteMode' => 'partial',
                    'location' => [
                        'locality' => 'Lyon',
                        'shortLabel' => 'Lyon (69)',
                        'label' => 'Lyon, Auvergne-Rhône-Alpes',
                    ],
                ],
            ],
        ];

        $response = new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        $client = new MockHttpClient($response);
        $provider = new FreeWorkJobProvider($client, new FreeWorkJobPostingParser(), 10);
        $source = new JobSource(
            new CandidateProfile(),
            'Free-Work — Symfony — Lyon',
            'https://www.free-work.com/fr/tech-it/jobs?query=Symfony&locations=Lyon&contracts=contractor',
            JobProviderType::FREE_WORK,
        );

        $offers = iterator_to_array($provider->fetch($source));

        self::assertTrue($provider->supports(JobProviderType::FREE_WORK));
        self::assertFalse($provider->supports(JobProviderType::INDEED));
        self::assertCount(1, $offers);
        self::assertSame('639398', $offers[0]->externalId);
        self::assertSame('Développeur Symfony Freelance', $offers[0]->title);
        self::assertSame('Tech Mission', $offers[0]->company);
        self::assertSame('Lyon', $offers[0]->location);
        self::assertSame('FREELANCE', $offers[0]->contractType);
        self::assertSame((int) round(550 * 218), $offers[0]->minimumSalary);
        self::assertSame((int) round(650 * 218), $offers[0]->maximumSalary);
        self::assertSame('HYBRID', $offers[0]->remotePolicy);
        self::assertSame(5, $offers[0]->yearsOfExperience);
        self::assertStringContainsString('Mission de 6 mois renouvelable', (string) $offers[0]->description);
        self::assertSame(
            'https://www.free-work.com/fr/tech-it/jobs/job-posting/developpeur-symfony-freelance',
            $offers[0]->url,
        );
    }
}
