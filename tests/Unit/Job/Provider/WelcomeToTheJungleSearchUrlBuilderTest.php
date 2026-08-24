<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\WelcomeToTheJungleSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WelcomeToTheJungleSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrl(): void
    {
        self::assertSame(
            'https://www.welcometothejungle.com/fr/jobs?query=D%C3%A9veloppeur+PHP&aroundQuery=Lyon',
            (new WelcomeToTheJungleSearchUrlBuilder())->build('Développeur PHP', 'Lyon'),
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new WelcomeToTheJungleSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'title' => ['', 'Lyon'];
        yield 'location' => ['PHP', ' '];
    }
}
