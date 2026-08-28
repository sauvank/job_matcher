<?php

declare(strict_types=1);

namespace App\Job\MessageHandler;

use App\Job\Message\SendDailyJobAlertsMessage;
use App\Job\Service\SchedulerExecutionTracker;
use App\Matching\Service\DailyJobAlertService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendDailyJobAlertsMessageHandler
{
    public function __construct(
        private DailyJobAlertService $alertService,
        private SchedulerExecutionTracker $tracker,
    ) {
    }

    public function __invoke(SendDailyJobAlertsMessage $message): void
    {
        $log = $this->tracker->start('job_alerts', SendDailyJobAlertsMessage::class, $message->triggeredBy);

        try {
            $sentCount = $this->alertService->sendDailyAlerts(
                force: $message->force,
                targetEmail: $message->targetEmail
            );

            $details = sprintf('%d email(s) d’alerte envoyé(s).', $sentCount);
            $this->tracker->complete($log, $sentCount, $details);
        } catch (\Throwable $e) {
            $this->tracker->fail($log, $e, 0);
            throw $e;
        }
    }
}
