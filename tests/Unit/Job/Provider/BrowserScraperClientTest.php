<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Provider;

use App\Job\Provider\BrowserScraperClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BrowserScraperClientTest extends TestCase
{
    public function testItReportsAvailabilityCorrectly(): void
    {
        $clientWithDsn = new BrowserScraperClient(new MockHttpClient(), 'http://browser:3000');
        self::assertTrue($clientWithDsn->isAvailable());

        $clientWithoutDsn = new BrowserScraperClient(new MockHttpClient(), null);
        self::assertFalse($clientWithoutDsn->isAvailable());

        $clientWithEmptyDsn = new BrowserScraperClient(new MockHttpClient(), '   ');
        self::assertFalse($clientWithEmptyDsn->isAvailable());
    }

    public function testItThrowsWhenNotAvailable(): void
    {
        $client = new BrowserScraperClient(new MockHttpClient(), null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Browser scraping service is not configured');

        $client->scrape('https://example.com');
    }

    public function testItScrapesSuccessfully(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse(
            json_encode(['html' => '<html><body>Offres</body></html>', 'status' => 200, 'finalUrl' => 'https://example.com'], JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        ));

        $client = new BrowserScraperClient($mockHttpClient, 'http://browser:3000');
        $html = $client->scrape('https://example.com');

        self::assertSame('<html><body>Offres</body></html>', $html);
    }

    public function testItThrowsOnServerError(): void
    {
        $mockHttpClient = new MockHttpClient(new MockResponse(
            json_encode(['error' => 'Timeout navigating to page'], JSON_THROW_ON_ERROR),
            ['http_code' => 500, 'response_headers' => ['content-type' => 'application/json']],
        ));

        $client = new BrowserScraperClient($mockHttpClient, 'http://browser:3000');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timeout navigating to page');

        $client->scrape('https://example.com');
    }
}
