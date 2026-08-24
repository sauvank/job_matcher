<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\ApecJobPostingParser;
use PHPUnit\Framework\TestCase;

final class ApecJobPostingParserTest extends TestCase
{
    public function testItExtractsOfferUrlsFromHtmlLinks(): void
    {
        $html = <<<'HTML'
            <div>
                <a href="/candidat/recherche-emploi.html/emploi/detail-offre/176000100W">Offre 1</a>
                <a href="/candidat/recherche-emploi.html/emploi/detail-offre/176000100W">Doublon</a>
                <a href="/candidat/recherche-emploi.html/emploi/detail-offre/176000200W">Offre 2</a>
            </div>
            HTML;

        $urls = (new ApecJobPostingParser())->extractOfferUrls($html, 2);

        self::assertSame([
            'https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/176000100W',
            'https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/176000200W',
        ], $urls);
    }

    public function testItNormalizesJobPostingJsonLd(): void
    {
        $jobPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Ingénieur d’études et développement PHP F/H',
            'description' => '<p>Développement de fonctionnalités <strong>backend</strong> en PHP 8.4.</p>',
            'baseSalary' => ['value' => ['minValue' => 45000, 'maxValue' => 52000]],
            'datePosted' => '2026-08-23T10:00:00Z',
            'validThrough' => '2026-09-23T10:00:00Z',
            'hiringOrganization' => ['name' => 'ESN Tech Solutions'],
            'jobLocation' => ['address' => ['addressLocality' => 'Toulouse', 'postalCode' => '31000']],
            'jobLocationType' => 'TELECOMMUTE',
            'employmentType' => 'FULL_TIME',
            'experienceRequirements' => ['monthsOfExperience' => 36],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new ApecJobPostingParser())->parseOffer(
            $html,
            'https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/176000100W',
        );

        self::assertSame('176000100W', $offer->externalId);
        self::assertSame('Ingénieur d’études et développement PHP F/H', $offer->title);
        self::assertSame('ESN Tech Solutions', $offer->company);
        self::assertSame('Toulouse 31000', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(45000, $offer->minimumSalary);
        self::assertSame(52000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(3, $offer->yearsOfExperience);
        self::assertSame('Développement de fonctionnalités backend en PHP 8.4.', $offer->description);
    }
}
