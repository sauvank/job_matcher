<?php

declare(strict_types=1);

namespace App\Candidate\Application\MessageHandler;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Application\Extraction\CvTextExtractorInterface;
use App\Candidate\Application\Message\ProcessCvMessage;
use App\Candidate\Application\Repository\CvDocumentRepositoryInterface;
use App\Candidate\Enum\CvStatus;
use App\Candidate\Translation\CandidateMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ProcessCvMessageHandler
{
    public function __construct(
        private CvDocumentRepositoryInterface $repository,
        private CvTextExtractorInterface $extractor,
        private CvAnalyzerInterface $analyzer,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessCvMessage $message): void
    {
        $document = $this->repository->get($message->cvDocumentId);
        if ($document === null) {
            throw new UnrecoverableMessageHandlingException(CandidateMessage::DOCUMENT_NOT_FOUND);
        }

        if (in_array($document->getStatus(), [CvStatus::READY, CvStatus::APPLIED], true)) {
            return;
        }

        $lock = $this->lockFactory->createLock('process-cv-'.$message->cvDocumentId, 120);
        if (!$lock->acquire()) {
            $this->logger->info(CandidateMessage::PROCESS_ALREADY_RUNNING, ['cvDocumentId' => $message->cvDocumentId]);

            return;
        }

        try {
            $document->markExtracting();
            $this->entityManager->flush();

            $text = $this->extractor->extract($document);
            $document->markAnalyzing($text);
            $this->entityManager->flush();

            $analysis = $this->analyzer->analyze($text);
            $document->completeAnalysis($analysis->toArray(), $this->analyzer->name());
            $this->entityManager->flush();

            $this->logger->info(CandidateMessage::PROCESS_COMPLETED, [
                'cvDocumentId' => $message->cvDocumentId,
                'analyzer' => $this->analyzer->name(),
            ]);
        } catch (CvExtractionException $exception) {
            $document->fail($exception->getMessage());
            $this->entityManager->flush();

            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } catch (CvAnalysisException $exception) {
            $document->fail($exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error($exception->getMessage(), [
                'cvDocumentId' => $message->cvDocumentId,
                'retryable' => $exception->retryable,
                'exception' => $exception,
            ]);

            if (!$exception->retryable) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }

            throw $exception;
        } catch (\Throwable $exception) {
            $document->fail(CandidateMessage::AI_PROCESS_FAILED);
            $this->entityManager->flush();
            $this->logger->error(CandidateMessage::AI_PROCESS_FAILED, [
                'cvDocumentId' => $message->cvDocumentId,
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
