<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Application\MessageHandler;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\Extraction\CvTextExtractorInterface;
use App\Candidate\Application\Message\ProcessCvMessage;
use App\Candidate\Application\MessageHandler\ProcessCvMessageHandler;
use App\Candidate\Application\Repository\CvDocumentRepositoryInterface;
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
}
