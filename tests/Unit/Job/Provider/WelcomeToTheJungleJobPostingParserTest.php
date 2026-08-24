<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\WelcomeToTheJungleJobPostingParser;
use PHPUnit\Framework\TestCase;

final class WelcomeToTheJungleJobPostingParserTest extends TestCase
{
    public function testItExtractsOfferUrlsFromHtmlLinks(): void
    {
        $html = <<<'HTML'
            <div>
                <a href="/fr/companies/payfit/jobs/senior-software-engineer-backend_paris">Offre 1</a>
                <a href="/fr/companies/payfit/jobs/senior-software-engineer-backend_paris">Doublon</a>
                <a href="/fr/companies/alan/jobs/fullstack-engineer_paris">Offre 2</a>
            </div>
            HTML;

        $urls = (new WelcomeToTheJungleJobPostingParser())->extractOfferUrls($html, 2);

        self::assertSame([
            'https://www.welcometothejungle.com/fr/companies/payfit/jobs/senior-software-engineer-backend_paris',
            'https://www.welcometothejungle.com/fr/companies/alan/jobs/fullstack-engineer_paris',
        ], $urls);
    }

    public function testItNormalizesJobPostingJsonLd(): void
    {
        $jobPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Senior Backend Engineer - PHP / Go',
            'description' => '<p>Join our core engineering team to build scalable microservices.</p>',
            'baseSalary' => ['value' => ['minValue' => 60000, 'maxValue' => 75000]],
            'datePosted' => '2026-08-21T09:00:00Z',
            'validThrough' => '2026-09-21T09:00:00Z',
            'hiringOrganization' => ['name' => 'ScaleUp Tech'],
            'jobLocation' => ['address' => ['addressLocality' => 'Paris', 'postalCode' => '75010']],
            'jobLocationType' => 'TELECOMMUTE',
            'employmentType' => 'FULL_TIME',
            'experienceRequirements' => ['monthsOfExperience' => 48],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new WelcomeToTheJungleJobPostingParser())->parseOffer(
            $html,
            'https://www.welcometothejungle.com/fr/companies/scaleup/jobs/senior-backend-engineer',
        );

        self::assertSame('scaleup_senior-backend-engineer', $offer->externalId);
        self::assertSame('Senior Backend Engineer - PHP / Go', $offer->title);
        self::assertSame('ScaleUp Tech', $offer->company);
        self::assertSame('Paris 75010', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(60000, $offer->minimumSalary);
        self::assertSame(75000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(4, $offer->yearsOfExperience);
        self::assertSame('Join our core engineering team to build scalable microservices.', $offer->description);
    }
}
