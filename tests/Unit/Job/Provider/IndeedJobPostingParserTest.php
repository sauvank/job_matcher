<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\IndeedJobPostingParser;
use PHPUnit\Framework\TestCase;

final class IndeedJobPostingParserTest extends TestCase
{
    public function testItExtractsOfferUrlsFromHtmlLinks(): void
    {
        $html = <<<'HTML'
            <div>
                <a href="/viewjob?jk=abcdef1234567890">Offre 1</a>
                <a href="/rc/clk?jk=abcdef1234567890">Doublon</a>
                <a data-jk="1122334455667788" href="#">Offre 2</a>
            </div>
            HTML;

        $urls = (new IndeedJobPostingParser())->extractOfferUrls($html, 2);

        self::assertSame([
            'https://fr.indeed.com/viewjob?jk=abcdef1234567890',
            'https://fr.indeed.com/viewjob?jk=1122334455667788',
        ], $urls);
    }

    public function testItExtractsOfferUrlsFromRssFeed(): void
    {
        $rss = <<<'XML'
            <?xml version="1.0" encoding="utf-8"?>
            <rss version="2.0">
                <channel>
                    <title>Indeed job search</title>
                    <item>
                        <title>Développeur PHP</title>
                        <link>https://fr.indeed.com/viewjob?jk=9988776655443322</link>
                        <description>Poste en CDI</description>
                    </item>
                </channel>
            </rss>
            XML;

        $urls = (new IndeedJobPostingParser())->extractOfferUrls($rss, 5);

        self::assertSame([
            'https://fr.indeed.com/viewjob?jk=9988776655443322',
        ], $urls);
    }

    public function testItNormalizesJobPostingJsonLd(): void
    {
        $jobPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => 'Lead Développeur Symfony',
            'description' => '<p>Conception et refonte d’architectures <strong>Symfony</strong>.</p>',
            'baseSalary' => ['value' => ['minValue' => 50000, 'maxValue' => 60000]],
            'datePosted' => '2026-08-22T09:00:00Z',
            'validThrough' => '2026-09-22T09:00:00Z',
            'hiringOrganization' => ['name' => 'Tech Scaleup'],
            'jobLocation' => ['address' => ['addressLocality' => 'Nantes', 'postalCode' => '44000']],
            'jobLocationType' => 'TELECOMMUTE',
            'employmentType' => 'FULL_TIME',
            'experienceRequirements' => ['monthsOfExperience' => 60],
        ];
        $html = '<script type="application/ld+json">'.json_encode($jobPosting, JSON_THROW_ON_ERROR).'</script>';

        $offer = (new IndeedJobPostingParser())->parseOffer(
            $html,
            'https://fr.indeed.com/viewjob?jk=abcdef1234567890',
        );

        self::assertSame('abcdef1234567890', $offer->externalId);
        self::assertSame('Lead Développeur Symfony', $offer->title);
        self::assertSame('Tech Scaleup', $offer->company);
        self::assertSame('Nantes 44000', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(50000, $offer->minimumSalary);
        self::assertSame(60000, $offer->maximumSalary);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(5, $offer->yearsOfExperience);
        self::assertSame('Conception et refonte d’architectures Symfony.', $offer->description);
    }

    public function testItFallsBackToDomParsingWhenJsonLdIsMissing(): void
    {
        $html = <<<'HTML'
            <html>
                <body>
                    <h1 data-testid="jobsearch-JobInfoHeader-title">Ingénieur DevOps Cloud</h1>
                    <div data-testid="inlineHeader-companyName">Cloud Solutions</div>
                    <div data-testid="inlineHeader-companyLocation">Bordeaux</div>
                    <div id="jobDescriptionText">
                        <p>Nous recrutons en CDI un ingénieur avec au moins 3 ans d'expérience sur AWS et Docker. Télétravail possible.</p>
                    </div>
                </body>
            </html>
            HTML;

        $offer = (new IndeedJobPostingParser())->parseOffer(
            $html,
            'https://fr.indeed.com/viewjob?jk=1234567890abcdef',
        );

        self::assertSame('1234567890abcdef', $offer->externalId);
        self::assertSame('Ingénieur DevOps Cloud', $offer->title);
        self::assertSame('Cloud Solutions', $offer->company);
        self::assertSame('Bordeaux', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame('REMOTE_AVAILABLE', $offer->remotePolicy);
        self::assertSame(3, $offer->yearsOfExperience);
        self::assertStringContainsString('AWS et Docker', (string) $offer->description);
    }
}
