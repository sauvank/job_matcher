<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Extraction;

use App\Candidate\Infrastructure\Extraction\IsolatedExtractionProtocol;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;

final class IsolatedExtractionProtocolTest extends TestCase
{
    private const FILENAME = '0198d421-caf2-7000-8000-123456789abc.pdf';

    public function testItRoundTripsARequest(): void
    {
        $protocol = new IsolatedExtractionProtocol();

        self::assertSame(
            ['filename' => self::FILENAME, 'mimeType' => 'application/pdf'],
            $protocol->parseRequest($protocol->request(self::FILENAME, 'application/pdf')),
        );
    }

    public function testItRejectsAnInvalidStoredFilename(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new IsolatedExtractionProtocol())->parseRequest(
            "{\"filename\":\"../secret.pdf\",\"mimeType\":\"application/pdf\"}\n",
        );
    }

    public function testItRoundTripsMultilineExtractedText(): void
    {
        $protocol = new IsolatedExtractionProtocol();
        $response = $protocol->parseResponse($protocol->success("Première ligne\nDeuxième ligne"));

        self::assertTrue($response->successful);
        self::assertSame("Première ligne\nDeuxième ligne", $response->text);
        self::assertNull($response->error);
    }

    public function testItRoundTripsAnExtractionError(): void
    {
        $protocol = new IsolatedExtractionProtocol();
        $response = $protocol->parseResponse($protocol->failure(CandidateMessage::PDF_EXTRACTION_FAILED));

        self::assertFalse($response->successful);
        self::assertNull($response->text);
        self::assertSame(CandidateMessage::PDF_EXTRACTION_FAILED, $response->error);
    }

    public function testItRejectsAnUntrustedErrorMessage(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        (new IsolatedExtractionProtocol())->parseResponse("{\"success\":false,\"error\":\"injected error\"}\n");
    }
}
