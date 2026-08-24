<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\IndeedSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IndeedSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrlFromTheCandidateProfile(): void
    {
        $url = (new IndeedSearchUrlBuilder())->build('Développeur PHP Symfony', 'Paris (75)');

        self::assertSame(
            'https://fr.indeed.com/emplois?q=D%C3%A9veloppeur+PHP+Symfony&l=Paris+%2875%29&sort=date&fromage=any',
            $url,
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingSearchCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new IndeedSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'missing title' => ['', 'Paris'];
        yield 'missing location' => ['Développeur PHP', '  '];
    }
}
