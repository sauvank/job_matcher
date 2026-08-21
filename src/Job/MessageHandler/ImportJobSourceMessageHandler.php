<?php

declare(strict_types=1);

namespace App\Job\MessageHandler;

use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobOffer;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\JobProviderRegistry;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ImportJobSourceMessageHandler
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private JobOfferRepositoryInterface $offerRepository,
        private JobProviderRegistry $providerRegistry,
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ImportJobSourceMessage $message): void
    {
        $source = $this->sourceRepository->get($message->jobSourceId);
        if ($source === null) {
            throw new UnrecoverableMessageHandlingException(JobMessage::SOURCE_NOT_FOUND);
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
            $importedCount = 0;

            foreach ($provider->fetch($source) as $normalizedOffer) {
                $offer = $this->offerRepository->findOneBySourceAndExternalId($source, $normalizedOffer->externalId);
                if ($offer === null) {
                    $offer = new JobOffer($source, $normalizedOffer);
                    $this->entityManager->persist($offer);
                } else {
                    $offer->updateFrom($normalizedOffer);
                }

                ++$importedCount;
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
