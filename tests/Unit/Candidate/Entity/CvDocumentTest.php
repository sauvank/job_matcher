<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Enum\CvStatus;
use PHPUnit\Framework\TestCase;

final class CvDocumentTest extends TestCase
{
    public function testItExposesTheProgressOfEachAnalysisStage(): void
    {
        $document = $this->document();
        self::assertSame(10, $document->getProcessingProgress());

        $document->markExtracting();
        self::assertSame(35, $document->getProcessingProgress());

        $document->markAnalyzing('Texte extrait du CV suffisamment long.');
        self::assertSame(70, $document->getProcessingProgress());

        $document->completeAnalysis(['summary' => 'Analyse'], 'fake');
        self::assertSame(100, $document->getProcessingProgress());
    }

    public function testItCanRequestAReanalysis(): void
    {
        $document = $this->document();
        $document->completeAnalysis(['summary' => 'Ancienne analyse'], 'fake');

        $document->requestReanalysis();

        self::assertSame(CvStatus::UPLOADED, $document->getStatus());
        self::assertNull($document->getAnalysisResult());
        self::assertNull($document->getAnalyzer());
        self::assertNull($document->getAnalyzedAt());
        self::assertNull($document->getErrorMessage());
    }

    public function testItCanUpdateAppliedDetails(): void
    {
        $document = $this->document();
        $document->markApplied('Développeur PHP', 'Paris', 4, ['CDI']);

        self::assertSame('Développeur PHP', $document->getAppliedTitle());
        self::assertSame('Paris', $document->getAppliedLocation());
        self::assertSame(4, $document->getAppliedYearsOfExperience());
        self::assertSame(['CDI'], $document->getAppliedContractTypes());

        $document->updateAppliedDetails('Tech Lead', 'Bordeaux', 8, ['FREELANCE', 'CDI']);

        self::assertSame('Tech Lead', $document->getAppliedTitle());
        self::assertSame('Bordeaux', $document->getAppliedLocation());
        self::assertSame(8, $document->getAppliedYearsOfExperience());
        self::assertSame(['FREELANCE', 'CDI'], $document->getAppliedContractTypes());
    }

    private function document(): CvDocument
    {
        return new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
    }
}
