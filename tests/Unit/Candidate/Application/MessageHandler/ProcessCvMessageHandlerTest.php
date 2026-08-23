<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Application\MessageHandler;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Application\Extraction\CvTextExtractorInterface;
use App\Candidate\Application\Message\ProcessCvMessage;
use App\Candidate\Application\MessageHandler\ProcessCvMessageHandler;
use App\Candidate\Application\Repository\CvDocumentRepositoryInterface;
use App\Candidate\Application\Security\CvMalwareScanException;
use App\Candidate\Application\Security\CvMalwareScannerInterface;
use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Enum\CvStatus;
use App\Candidate\Infrastructure\Analyzer\FakeCvAnalyzer;
use App\Candidate\Translation\CandidateMessage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class ProcessCvMessageHandlerTest extends TestCase
{
    public function testItExtractsAndAnalyzesTheCvIdempotently(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $repository = new class($document) implements CvDocumentRepositoryInterface {
            public function __construct(private readonly CvDocument $document)
            {
            }

            public function get(int $id): ?CvDocument
            {
                return $id === 12 ? $this->document : null;
            }
        };
        $extractor = new class implements CvTextExtractorInterface {
            public function extract(CvDocument $document): string
            {
                return 'Développeur PHP Symfony et Docker depuis 5 ans, spécialisé dans les API backend.';
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $handler = new ProcessCvMessageHandler(
            $repository,
            $this->createStub(CvMalwareScannerInterface::class),
            $extractor,
            new FakeCvAnalyzer(),
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        $handler(new ProcessCvMessage(12));
        $handler(new ProcessCvMessage(12));

        self::assertSame(CvStatus::READY, $document->getStatus());
        self::assertSame('fake', $document->getAnalyzer());
        self::assertNotNull($document->getAnalysisResult());
    }

    public function testItStoresAPreciseNonRetryableAnalysisError(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $repository = new class($document) implements CvDocumentRepositoryInterface {
            public function __construct(private readonly CvDocument $document)
            {
            }

            public function get(int $id): ?CvDocument
            {
                return $id === 12 ? $this->document : null;
            }
        };
        $extractor = new class implements CvTextExtractorInterface {
            public function extract(CvDocument $document): string
            {
                return 'CV de test suffisamment long pour être analysé.';
            }
        };
        $analyzer = $this->createStub(CvAnalyzerInterface::class);
        $analyzer->method('analyze')->willThrowException(
            new CvAnalysisException(CandidateMessage::OPENAI_QUOTA_EXCEEDED, false),
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $handler = new ProcessCvMessageHandler(
            $repository,
            $this->createStub(CvMalwareScannerInterface::class),
            $extractor,
            $analyzer,
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        try {
            $handler(new ProcessCvMessage(12));
            self::fail('Une exception non récupérable était attendue.');
        } catch (UnrecoverableMessageHandlingException) {
            self::assertSame(CvStatus::FAILED, $document->getStatus());
            self::assertSame(CandidateMessage::OPENAI_QUOTA_EXCEEDED, $document->getErrorMessage());
        }
    }

    public function testItRejectsAnInfectedCvBeforeExtraction(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $repository = new class($document) implements CvDocumentRepositoryInterface {
            public function __construct(private readonly CvDocument $document)
            {
            }

            public function get(int $id): ?CvDocument
            {
                return $id === 12 ? $this->document : null;
            }
        };
        $scanner = $this->createStub(CvMalwareScannerInterface::class);
        $scanner->method('scan')->willThrowException(
            new CvMalwareScanException(CandidateMessage::MALWARE_DETECTED, false),
        );
        $extractor = $this->createMock(CvTextExtractorInterface::class);
        $extractor->expects(self::never())->method('extract');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $handler = new ProcessCvMessageHandler(
            $repository,
            $scanner,
            $extractor,
            new FakeCvAnalyzer(),
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        try {
            $handler(new ProcessCvMessage(12));
            self::fail('Une exception non récupérable était attendue.');
        } catch (UnrecoverableMessageHandlingException) {
            self::assertSame(CvStatus::FAILED, $document->getStatus());
            self::assertSame(CandidateMessage::MALWARE_DETECTED, $document->getErrorMessage());
        }
    }

    public function testItRetriesWhenTheMalwareScannerIsUnavailable(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $repository = new class($document) implements CvDocumentRepositoryInterface {
            public function __construct(private readonly CvDocument $document)
            {
            }

            public function get(int $id): ?CvDocument
            {
                return $id === 12 ? $this->document : null;
            }
        };
        $expectedException = new CvMalwareScanException(CandidateMessage::MALWARE_SCAN_UNAVAILABLE, true);
        $scanner = $this->createStub(CvMalwareScannerInterface::class);
        $scanner->method('scan')->willThrowException($expectedException);
        $extractor = $this->createMock(CvTextExtractorInterface::class);
        $extractor->expects(self::never())->method('extract');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $handler = new ProcessCvMessageHandler(
            $repository,
            $scanner,
            $extractor,
            new FakeCvAnalyzer(),
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        try {
            $handler(new ProcessCvMessage(12));
            self::fail('Une exception récupérable était attendue.');
        } catch (CvMalwareScanException $exception) {
            self::assertSame($expectedException, $exception);
            self::assertSame(CvStatus::FAILED, $document->getStatus());
            self::assertSame(CandidateMessage::MALWARE_SCAN_UNAVAILABLE, $document->getErrorMessage());
        }
    }

    public function testItRetriesWhenTheIsolatedExtractorIsUnavailable(): void
    {
        $document = new CvDocument(
            new CandidateProfile(),
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1234,
            str_repeat('a', 64),
        );
        $repository = new class($document) implements CvDocumentRepositoryInterface {
            public function __construct(private readonly CvDocument $document)
            {
            }

            public function get(int $id): ?CvDocument
            {
                return $id === 12 ? $this->document : null;
            }
        };
        $expectedException = new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
        $extractor = $this->createStub(CvTextExtractorInterface::class);
        $extractor->method('extract')->willThrowException($expectedException);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('flush');
        $handler = new ProcessCvMessageHandler(
            $repository,
            $this->createStub(CvMalwareScannerInterface::class),
            $extractor,
            new FakeCvAnalyzer(),
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        try {
            $handler(new ProcessCvMessage(12));
            self::fail('Une exception récupérable était attendue.');
        } catch (CvExtractionException $exception) {
            self::assertSame($expectedException, $exception);
            self::assertSame(CvStatus::FAILED, $document->getStatus());
            self::assertSame(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, $document->getErrorMessage());
        }
    }
}
