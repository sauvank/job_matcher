<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\LinkedInSearchUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinkedInSearchUrlBuilderTest extends TestCase
{
    public function testItBuildsAnExternalSearchUrl(): void
    {
        self::assertSame(
            'https://www.linkedin.com/jobs/search/?keywords=D%C3%A9veloppeur+PHP&location=Lyon',
            (new LinkedInSearchUrlBuilder())->build('Développeur PHP', 'Lyon'),
        );
    }

    #[DataProvider('missingCriteria')]
    public function testItRejectsMissingCriteria(string $title, string $location): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LinkedInSearchUrlBuilder())->build($title, $location);
    }

    /** @return iterable<string, array{string, string}> */
    public static function missingCriteria(): iterable
    {
        yield 'title' => ['', 'Lyon'];
        yield 'location' => ['PHP', ' '];
    }
}
