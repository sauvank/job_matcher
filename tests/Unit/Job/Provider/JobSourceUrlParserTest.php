<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Enum\JobProviderType;
use App\Job\Provider\JobSourceUrlParser;
use App\Job\Translation\JobMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobSourceUrlParserTest extends TestCase
{
    public function testItDetectsAHelloWorkSearchUrl(): void
    {
        $url = 'https://www.hellowork.com/fr-fr/emploi/recherche.html?k=Developpeur+PHP&l=Lyon';

        self::assertSame(JobProviderType::HELLOWORK, (new JobSourceUrlParser())->detect($url));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrls(): iterable
    {
        yield 'HTTP is forbidden' => ['http://www.hellowork.com/fr-fr/emploi/recherche.html?k=PHP'];
        yield 'Another host is forbidden' => ['https://example.com/fr-fr/emploi/recherche.html?k=PHP'];
        yield 'Missing keyword' => ['https://www.hellowork.com/fr-fr/emploi/recherche.html?l=Lyon'];
    }

    #[DataProvider('invalidUrls')]
    public function testItRejectsAnInvalidOrUnsupportedUrl(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^'.preg_quote(JobMessage::INVALID_URL, '/').'|'.preg_quote(JobMessage::UNSUPPORTED_PROVIDER, '/').'$/');

        (new JobSourceUrlParser())->detect($url);
    }
}
