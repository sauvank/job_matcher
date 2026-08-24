<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\WelcomeToTheJungleSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WelcomeToTheJungleSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrlFromTheCandidateProfile(): void
    {
        $url = (new WelcomeToTheJungleSearchUrlBuilder())->build('Backend Engineer', 'Paris');

        self::assertSame(
            'https://www.welcometothejungle.com/fr/jobs?query=Backend+Engineer&aroundQuery=Paris',
            $url,
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingSearchCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WelcomeToTheJungleSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'missing title' => ['', 'Paris'];
        yield 'missing location' => ['Backend Engineer', '  '];
    }
}
