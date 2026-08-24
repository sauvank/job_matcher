<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\FranceTravailJobPostingParser;
use PHPUnit\Framework\TestCase;

final class FranceTravailJobPostingParserTest extends TestCase
{
    public function testItExtractsOfferUrlsFromHtmlLinks(): void
    {
        $html = <<<'HTML'
            <div>
                <a href="/offres/recherche/detail/184ABCD">Offre 1</a>
                <a href="/offres/recherche/detail/184ABCD">Doublon</a>
                <a href="/offres/recherche/detail/184WXYZ">Offre 2</a>
            </div>
            HTML;

        $urls = (new FranceTravailJobPostingParser())->extractOfferUrls($html, 2);

        self::assertSame([
            'https://candidat.francetravail.fr/offres/recherche/detail/184ABCD',
            'https://candidat.francetravail.fr/offres/recherche/detail/184WXYZ',
        ], $urls);
    }

    public function testItNormalizesJobPostingJsonLd(): void
    {
        $jobPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Développeur / Développeuse web PHP Symfony',
            'description' => '<p>Poste de développeur web au sein d’une équipe agile. 5 ans d’expérience demandés.</p>',
            'baseSalary' => ['value' => ['minValue' => 40000, 'maxValue' => 48000]],
            'datePosted' => '2026-08-22T08:00:00Z',
            'validThrough' => '2026-09-22T08:00:00Z',
            'hiringOrganization' => ['name' => 'Agence Digitale'],
            'jobLocation' => ['address' => ['addressLocality' => 'Bordeaux', 'postalCode' => '33000']],
            'jobLocationType' => 'TELECOMMUTE',
            'employmentType' => 'FULL_TIME',
            'experienceRequirements' => ['monthsOfExperience' => 60],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new FranceTravailJobPostingParser())->parseOffer(
            $html,
            'https://candidat.francetravail.fr/offres/recherche/detail/184ABCD',
        );

        self::assertSame('184ABCD', $offer->externalId);
        self::assertSame('Développeur / Développeuse web PHP Symfony', $offer->title);
        self::assertSame('Agence Digitale', $offer->company);
        self::assertSame('Bordeaux 33000', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(40000, $offer->minimumSalary);
        self::assertSame(48000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(5, $offer->yearsOfExperience);
        self::assertSame('Poste de développeur web au sein d’une équipe agile. 5 ans d’expérience demandés.', $offer->description);
    }

    public function testItNormalizesTheMicrodataUsedByCurrentJobPages(): void
    {
        $html = <<<'HTML'
            <main>
                <h1><span>Offre n° 212VBFN</span><br><span itemprop="title">Développeur PHP Symfony</span></h1>
                <p itemprop="jobLocation"><span itemprop="address"><span itemprop="name">69 - Lyon</span></span></p>
                <div itemprop="description"><p>Développement PHP et Symfony. 3 ans d’expérience demandés.</p></div>
                <span itemprop="hiringOrganization"><span content="ACME" itemprop="name"></span></span>
                <span content="2026-08-24" itemprop="datePosted"></span>
                <span content="2026-09-24" itemprop="validThrough"></span>
                <span itemprop="baseSalary"><span itemprop="value"><span content="38000" itemprop="minValue"></span><span content="45000" itemprop="maxValue"></span></span></span>
                <div>CDI avec télétravail partiel</div>
            </main>
            HTML;

        $offer = (new FranceTravailJobPostingParser())->parseOffer(
            $html,
            'https://candidat.francetravail.fr/offres/recherche/detail/212VBFN',
        );

        self::assertSame('Développeur PHP Symfony', $offer->title);
        self::assertSame('ACME', $offer->company);
        self::assertSame('69 - Lyon', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(38000, $offer->minimumSalary);
        self::assertSame(45000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(3, $offer->yearsOfExperience);
        self::assertSame('2026-08-24', $offer->publishedAt?->format('Y-m-d'));
    }
}
