<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Extraction;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Infrastructure\Extraction\IsolatedCvTextExtractor;
use App\Candidate\Infrastructure\Extraction\IsolatedExtractionProtocol;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;

final class IsolatedCvTextExtractorTest extends TestCase
{
    public function testAnUnavailableSocketProducesARetryableError(): void
    {
        $extractor = new IsolatedCvTextExtractor(
            new IsolatedExtractionProtocol(),
            'unix://'.sys_get_temp_dir().'/missing-extractor-'.bin2hex(random_bytes(8)).'.sock',
            0.1,
            3_145_728,
        );
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            '0198d421-caf2-7000-8000-123456789abc.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );

        try {
            $extractor->extract($document);
            self::fail('Une erreur récupérable était attendue.');
        } catch (CvExtractionException $exception) {
            self::assertTrue($exception->retryable);
            self::assertSame(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, $exception->getMessage());
        }
    }
}
