<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Enum\CvStatus;
use PHPUnit\Framework\TestCase;

final class CvDocumentTest extends TestCase
{
    public function testItCanRequestAReanalysis(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $document->completeAnalysis(['summary' => 'Ancienne analyse'], 'fake');

        $document->requestReanalysis();

        self::assertSame(CvStatus::UPLOADED, $document->getStatus());
        self::assertNull($document->getAnalysisResult());
        self::assertNull($document->getAnalyzer());
        self::assertNull($document->getAnalyzedAt());
        self::assertNull($document->getErrorMessage());
    }
}
