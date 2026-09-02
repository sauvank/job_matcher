<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\FreeWorkSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FreeWorkSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsASearchUrl(): void
    {
        self::assertSame(
            'https://www.free-work.com/fr/tech-it/jobs?query=D%C3%A9veloppeur+PHP&locations=Lyon&contracts=contractor',
            (new FreeWorkSearchUrlBuilder())->build('Développeur PHP', 'Lyon'),
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FreeWorkSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'title' => ['', 'Lyon'];
        yield 'location' => ['PHP', ' '];
    }
}
