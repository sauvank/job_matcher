<?php

declare(strict_types=1);

namespace App\Job\MessageHandler;

use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Message\RefreshJobSourcesMessage;
use App\Job\Service\SchedulerExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RefreshJobSourcesMessageHandler
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private SchedulerExecutionTracker $tracker,
    ) {
    }

    public function __invoke(RefreshJobSourcesMessage $message): void
    {
        $log = $this->tracker->start('job_sync', RefreshJobSourcesMessage::class, $message->triggeredBy);
        $queuedCount = 0;

        try {
            $this->sourceRepository->recoverAllStuckSyncs();
            $sources = $this->sourceRepository->findEnabled();

            foreach ($sources as $source) {
                if ($source->isSyncPending()) {
                    continue;
                }

                $source->queueSync();
                $this->entityManager->flush();

                $sourceId = $source->getId();
                if ($sourceId === null) {
                    throw new \LogicException('A persisted job source must have an identifier.');
                }

                $this->messageBus->dispatch(new ImportJobSourceMessage($sourceId));
                ++$queuedCount;
            }

            $details = sprintf('%d source(s) mise(s) en file pour synchronisation.', $queuedCount);
            $this->tracker->complete($log, $queuedCount, $details);
        } catch (\Throwable $e) {
            $this->tracker->fail($log, $e, $queuedCount);
            throw $e;
        }
    }
}
