<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\FranceTravailSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FranceTravailSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrlFromTheCandidateProfile(): void
    {
        $url = (new FranceTravailSearchUrlBuilder())->build('Développeur Symfony', 'Paris');

        self::assertSame(
            'https://candidat.francetravail.fr/offres/recherche?motsCles=D%C3%A9veloppeur+Symfony&lieux=Paris&offresPartenaires=true&tri=0',
            $url,
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingSearchCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FranceTravailSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'missing title' => ['', 'Paris'];
        yield 'missing location' => ['Développeur Symfony', '  '];
    }
}
