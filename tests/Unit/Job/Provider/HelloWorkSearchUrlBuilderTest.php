<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\HelloWorkSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelloWorkSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrlFromTheCandidateProfile(): void
    {
        $url = (new HelloWorkSearchUrlBuilder())->build('Développeur back-end PHP', 'Lyon 69000');

        self::assertSame(
            'https://www.hellowork.com/fr-fr/emploi/recherche.html?k=D%C3%A9veloppeur+back-end+PHP&k_autocomplete=&l=Lyon+69000&l_autocomplete=&st=relevance&msa=0&d=all',
            $url,
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingSearchCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HelloWorkSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'missing title' => ['', 'Lyon'];
        yield 'missing location' => ['Développeur PHP', '  '];
    }
}
