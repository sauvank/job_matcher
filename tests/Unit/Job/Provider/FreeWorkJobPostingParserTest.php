<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\FreeWorkJobPostingParser;
use PHPUnit\Framework\TestCase;

final class FreeWorkJobPostingParserTest extends TestCase
{
    public function testItParsesAnnualSalaryAndFullRemote(): void
    {
        $parser = new FreeWorkJobPostingParser();
        $offer = $parser->parseOffer([
            'id' => 12345,
            'title' => 'Lead Dev PHP',
            'slug' => 'lead-dev-php',
            'description' => '<p>Description du poste</p>',
            'publishedAt' => '2026-09-01T12:00:00+02:00',
            'expiredAt' => null,
            'company' => ['name' => 'Acme Corp'],
            'contracts' => ['permanent'],
            'experienceLevel' => 'expert',
            'minAnnualSalary' => 60000,
            'maxAnnualSalary' => 70000,
            'minDailySalary' => null,
            'maxDailySalary' => null,
            'remoteMode' => 'full',
            'location' => [
                'locality' => 'Paris',
            ],
        ]);

        self::assertSame('12345', $offer->externalId);
        self::assertSame('Lead Dev PHP', $offer->title);
        self::assertSame('Acme Corp', $offer->company);
        self::assertSame('Paris', $offer->location);
        self::assertSame('CDI', $offer->contractType);
        self::assertSame(60000, $offer->minimumSalary);
        self::assertSame(70000, $offer->maximumSalary);
        self::assertSame('REMOTE', $offer->remotePolicy);
        self::assertSame(8, $offer->yearsOfExperience);
    }
}
