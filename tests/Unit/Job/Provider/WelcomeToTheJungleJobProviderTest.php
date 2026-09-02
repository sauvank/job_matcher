<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\WelcomeToTheJungleJobPostingParser;
use App\Job\Provider\WelcomeToTheJungleJobProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WelcomeToTheJungleJobProviderTest extends TestCase
{
    public function testItImportsStructuredOffersFromThePublicSearchIndex(): void
    {
        $payload = [
            'results' => [[
                'hits' => [[
                    'reference' => 'WEBNE_ZkaaOK6',
                    'slug' => 'developpeur-senior-php-symfony_lille_WEBNE_ZkaaOK6',
                    'name' => 'Développeur senior PHP Symfony',
                    'profile' => '<p>Minimum 3,5 ans avec PHP et Symfony.</p>',
                    'published_at' => '2026-08-17T07:00:00+02:00',
                    'organization' => ['name' => 'Webnet', 'slug' => 'webnet'],
                    'contract_type' => 'FULL_TIME',
                    'contract_type_names' => ['fr' => 'CDI'],
                    'experience_level_minimum' => 3.5,
                    'salary_yearly_minimum' => 37000,
                    'salary_yearly_maximum' => 40000,
                    'offices' => [['city' => 'Lille'], ['city' => 'Paris'], ['city' => 'Lille']],
                    'remote' => 'partial',
                ]],
            ]],
        ];
        $response = new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        $client = new MockHttpClient($response);
        $provider = new WelcomeToTheJungleJobProvider($client, new WelcomeToTheJungleJobPostingParser(), 10);
        $source = new JobSource(
            new CandidateProfile(),
            'Welcome to the Jungle — PHP — Lyon',
            'https://www.welcometothejungle.com/fr/jobs?query=PHP&aroundQuery=Lyon',
            JobProviderType::WELCOME_TO_THE_JUNGLE,
        );

        $offers = iterator_to_array($provider->fetch($source));

        self::assertTrue($provider->supports(JobProviderType::WELCOME_TO_THE_JUNGLE));
        self::assertFalse($provider->supports(JobProviderType::INDEED));
        self::assertCount(1, $offers);
        self::assertSame('WEBNE_ZkaaOK6', $offers[0]->externalId);
        self::assertSame('Développeur senior PHP Symfony', $offers[0]->title);
        self::assertSame('Webnet', $offers[0]->company);
        self::assertSame('Lille, Paris', $offers[0]->location);
        self::assertSame('CDI', $offers[0]->contractType);
        self::assertSame(37000, $offers[0]->minimumSalary);
        self::assertSame(40000, $offers[0]->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offers[0]->remotePolicy);
        self::assertSame(4, $offers[0]->yearsOfExperience);
        self::assertSame('Minimum 3,5 ans avec PHP et Symfony.', $offers[0]->description);
        self::assertSame(
            'https://www.welcometothejungle.com/fr/companies/webnet/jobs/developpeur-senior-php-symfony_lille_WEBNE_ZkaaOK6',
            $offers[0]->url,
        );

        $requestBody = $response->getRequestOptions()['body'] ?? null;
        self::assertIsString($requestBody);
        self::assertStringContainsString('query=PHP', $requestBody);
        self::assertStringContainsString('aroundQuery=Lyon', $requestBody);
        self::assertStringContainsString('website.reference%3Awttj_fr', $requestBody);
    }
}
