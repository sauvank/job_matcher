<?php

declare(strict_types=1);

namespace App\Job\MessageHandler;

use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobOffer;
use App\Job\Enum\JobOfferAvailability;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\JobProviderRegistry;
use App\Job\Translation\JobMessage;
use App\Matching\Enum\SemanticAnalysisStatus;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Service\MatchJobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ImportJobSourceMessageHandler
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private JobOfferRepositoryInterface $offerRepository,
        private JobProviderRegistry $providerRegistry,
        private MatchJobOfferService $matchService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportJobSourceMessage $message): void
    {
        $source = $this->sourceRepository->get($message->jobSourceId);
        if ($source === null) {
            $this->logger->info(JobMessage::SOURCE_NOT_FOUND, ['jobSourceId' => $message->jobSourceId]);

            return;
        }

        if (!$source->isEnabled()) {
            return;
        }

        $lock = $this->lockFactory->createLock('import-job-source-'.$message->jobSourceId, 180);
        if (!$lock->acquire()) {
            $this->logger->info(JobMessage::SYNC_ALREADY_RUNNING, ['jobSourceId' => $message->jobSourceId]);

            return;
        }

        try {
            $source->markSyncStarted();
            $this->entityManager->flush();

            $provider = $this->providerRegistry->get($source->getProvider());
            $profile = $source->getCandidateProfile();
            $importedCount = 0;
            $seenExternalIds = [];

            foreach ($provider->fetch($source) as $normalizedOffer) {
                $seenExternalIds[$normalizedOffer->externalId] = true;
                $offer = $this->offerRepository->findOneBySourceAndExternalId($source, $normalizedOffer->externalId);
                $contentChanged = $offer === null || !$offer->hasSameContentAs($normalizedOffer);
                if ($offer === null) {
                    $offer = new JobOffer($source, $normalizedOffer);
                    $this->entityManager->persist($offer);
                } else {
                    $offer->updateFrom($normalizedOffer);
                }

                $match = $this->matchService->match($profile, $offer);
                $analysisStatus = $match->getSemanticAnalysisStatus();
                $shouldQueueAnalysis = !in_array($analysisStatus, [SemanticAnalysisStatus::QUEUED, SemanticAnalysisStatus::RUNNING], true)
                    && ($contentChanged || in_array($analysisStatus, [SemanticAnalysisStatus::NOT_REQUESTED, SemanticAnalysisStatus::FAILED], true));
                if ($shouldQueueAnalysis) {
                    $match->queueSemanticAnalysis();
                }

                ++$importedCount;
                $source->recordProcessedOffer();
                $this->entityManager->flush();

                if ($shouldQueueAnalysis) {
                    $matchId = $match->getId();
                    if ($matchId === null) {
                        throw new \LogicException('A persisted job match must have an identifier.');
                    }
                    $this->messageBus->dispatch(new AnalyzeJobMatchMessage($matchId));
                }
            }

            foreach ($this->offerRepository->findActiveBySource($source) as $offer) {
                if (isset($seenExternalIds[$offer->getExternalId()])) {
                    continue;
                }

                if ($offer->hasExpiredBy(new \DateTimeImmutable()) || $provider->checkAvailability($offer) === JobOfferAvailability::EXPIRED) {
                    $offer->markExpired();
                }
            }

            $source->completeSync();
            $this->entityManager->flush();
            $this->logger->info('job.source.sync_completed', [
                'jobSourceId' => $message->jobSourceId,
                'offerCount' => $importedCount,
            ]);
        } catch (\Throwable $exception) {
            $source->failSync(JobMessage::SYNC_FAILED);
            $this->entityManager->flush();
            $this->logger->error(JobMessage::SYNC_FAILED, [
                'jobSourceId' => $message->jobSourceId,
                'exception' => $exception,
            ]);

            throw $exception;
        } finally {
            $lock->release();
        }
    }
}
