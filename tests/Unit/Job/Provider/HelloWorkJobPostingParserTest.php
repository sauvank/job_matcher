<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\HelloWorkJobPostingParser;
use PHPUnit\Framework\TestCase;

final class HelloWorkJobPostingParserTest extends TestCase
{
    public function testItExtractsUniqueOfferUrlsWithALimit(): void
    {
        $html = <<<'HTML'
            <a href="/fr-fr/emplois/12345678.html">Offre 1</a>
            <a href="/fr-fr/emplois/12345678.html">Doublon</a>
            <a href="/fr-fr/emplois/87654321.html">Offre 2</a>
            HTML;

        self::assertSame(
            ['https://www.hellowork.com/fr-fr/emplois/12345678.html'],
            (new HelloWorkJobPostingParser())->extractOfferUrls($html, 1),
        );
    }

    public function testItNormalizesAJobPosting(): void
    {
        $jobPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Développeur PHP Symfony H/F',
            'description' => '<p>Développement d’API avec <strong>Symfony</strong>.</p>',
            'baseSalary' => ['value' => ['minValue' => 40000, 'maxValue' => 50000]],
            'datePosted' => '2026-08-20T08:00:00Z',
            'validThrough' => '2026-09-20T08:00:00Z',
            'hiringOrganization' => ['name' => 'Entreprise Test'],
            'jobLocation' => ['address' => ['addressLocality' => 'Lyon', 'postalCode' => '69000']],
            'jobLocationType' => 'TELECOMMUTE',
            'experienceRequirements' => ['monthsOfExperience' => 48],
        ];
        $html = '<script>window.dataLayer.push({"contrat":"CDI"});</script>'
            .'<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new HelloWorkJobPostingParser())->parseOffer(
            $html,
            'https://www.hellowork.com/fr-fr/emplois/12345678.html',
        );

        self::assertSame('12345678', $offer->externalId);
        self::assertSame('Développeur PHP Symfony H/F', $offer->title);
        self::assertSame('Entreprise Test', $offer->company);
        self::assertSame('Lyon 69000', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(40000, $offer->minimumSalary);
        self::assertSame(50000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(4, $offer->yearsOfExperience);
        self::assertSame('Développement d’API avec Symfony.', $offer->description);
    }

    public function testItPrefersTheExplicitExperienceRequirementWhenStructuredDataIsInconsistent(): void
    {
        $jobPosting = [
            '@type' => 'JobPosting',
            'title' => 'Développeur Full Stack PHP React',
            'description' => '<p>Un cabinet créé il y a 15 ans.</p>',
            'qualifications' => '<p>Vous justifiez d’au moins 2 ans d’expérience en développement Full Stack.</p>',
            'experienceRequirements' => ['monthsOfExperience' => 12],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new HelloWorkJobPostingParser())->parseOffer(
            $html,
            'https://www.hellowork.com/fr-fr/emplois/82138842.html',
        );

        self::assertSame(2, $offer->yearsOfExperience);
    }

    public function testItDoesNotClaimOneYearIsEnoughForAnExplicitlyExperiencedProfile(): void
    {
        $jobPosting = [
            '@type' => 'JobPosting',
            'title' => 'Tech Lead PHP',
            'description' => '<p>Pilotage de la roadmap technique.</p>',
            'qualifications' => '<p>Expérience significative en tant que Tech Lead ou référent technique.</p>',
            'experienceRequirements' => ['monthsOfExperience' => 12],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new HelloWorkJobPostingParser())->parseOffer(
            $html,
            'https://www.hellowork.com/fr-fr/emplois/79315367.html',
        );

        self::assertNull($offer->yearsOfExperience);
    }

    public function testItReadsAnExplicitRequirementWrittenInYears(): void
    {
        $jobPosting = [
            '@type' => 'JobPosting',
            'title' => 'Développeur Symfony Angular',
            'description' => '<p>Développement full-stack.</p>',
            'qualifications' => '<p>Vous disposez d’au moins 5 années d’expérience dans le développement PHP.</p>',
            'experienceRequirements' => ['monthsOfExperience' => 12],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new HelloWorkJobPostingParser())->parseOffer(
            $html,
            'https://www.hellowork.com/fr-fr/emplois/79162910.html',
        );

        self::assertSame(5, $offer->yearsOfExperience);
    }
}
