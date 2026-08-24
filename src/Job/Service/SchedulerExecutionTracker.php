<?php

declare(strict_types=1);

namespace App\Job\Service;

use App\Job\Entity\SchedulerExecutionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class SchedulerExecutionTracker
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function start(string $scheduleName, string $commandOrMessage, string $triggeredBy = 'scheduler'): SchedulerExecutionLog
    {
        $log = new SchedulerExecutionLog($scheduleName, $commandOrMessage, $triggeredBy);
        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->logger?->info(sprintf('Cron task started: %s (%s) triggered by %s', $scheduleName, $commandOrMessage, $triggeredBy));

        return $log;
    }

    public function complete(SchedulerExecutionLog $log, int $processedCount = 0, ?string $details = null): void
    {
        $log->complete($processedCount, $details);
        $this->entityManager->flush();

        $this->logger?->info(sprintf(
            'Cron task completed: %s in %s (Processed: %d)',
            $log->getScheduleName(),
            $log->getDurationFormatted(),
            $processedCount,
        ));
    }

    public function fail(SchedulerExecutionLog $log, \Throwable|string $error, int $processedCount = 0, ?string $details = null): void
    {
        $errorMessage = $error instanceof \Throwable ? sprintf('%s: %s in %s:%d', $error::class, $error->getMessage(), $error->getFile(), $error->getLine()) : (string) $error;

        $log->fail($errorMessage, $processedCount, $details);
        $this->entityManager->flush();

        $this->logger?->error(sprintf(
            'Cron task failed: %s after %s with error: %s',
            $log->getScheduleName(),
            $log->getDurationFormatted(),
            $errorMessage,
        ));
    }
}
