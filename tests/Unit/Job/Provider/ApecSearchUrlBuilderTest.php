<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\ApecSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApecSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrlFromTheCandidateProfile(): void
    {
        $url = (new ApecSearchUrlBuilder())->build('Développeur PHP Symfony', 'Lyon');

        self::assertSame(
            'https://www.apec.fr/candidat/recherche-emploi.html/emploi?motsCles=D%C3%A9veloppeur+PHP+Symfony&lieux=Lyon',
            $url,
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingSearchCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ApecSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'missing title' => ['', 'Lyon'];
        yield 'missing location' => ['Développeur PHP', '  '];
    }
}
