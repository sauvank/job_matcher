<?php

declare(strict_types=1);

namespace App\Matching\MessageHandler;

use App\Matching\Application\Analyzer\JobSemanticAnalyzerInterface;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Enum\SemanticAnalysisStatus;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Translation\MatchingMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class AnalyzeJobMatchMessageHandler
{
    public function __construct(
        private JobMatchRepositoryInterface $repository,
        private JobSemanticAnalyzerInterface $analyzer,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AnalyzeJobMatchMessage $message): void
    {
        $match = $this->repository->get($message->jobMatchId);
        if ($match === null) {
            $this->logger->info(MatchingMessage::MATCH_NOT_FOUND, ['jobMatchId' => $message->jobMatchId]);

            return;
        }
        if (!in_array($match->getSemanticAnalysisStatus(), [SemanticAnalysisStatus::QUEUED, SemanticAnalysisStatus::RUNNING], true)) {
            return;
        }
        if (!$match->belongsToActiveCv()) {
            $match->cancelQueuedSemanticAnalysis();
            $this->entityManager->flush();

            return;
        }

        $lock = $this->lockFactory->createLock('analyze-job-match-'.$message->jobMatchId, 180);
        if (!$lock->acquire()) {
            return;
        }

        try {
            $match->startSemanticAnalysis();
            $this->entityManager->flush();
            $analysis = $this->analyzer->analyze($match->getCandidateProfile(), $match->getJobOffer());
            $match->completeSemanticAnalysis($analysis, $this->analyzer->name());
            $this->entityManager->flush();
        } catch (SemanticAnalysisException $exception) {
            $match->failSemanticAnalysis($exception->getMessage());
            $this->entityManager->flush();
            $this->logger->error($exception->getMessage(), ['jobMatchId' => $message->jobMatchId, 'retryable' => $exception->retryable]);
            if (!$exception->retryable) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }
            throw $exception;
        } catch (\Throwable $exception) {
            $match->failSemanticAnalysis(MatchingMessage::SEMANTIC_ANALYSIS_FAILED);
            $this->entityManager->flush();
            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
