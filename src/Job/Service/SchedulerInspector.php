<?php

declare(strict_types=1);

namespace App\Job\Service;

use App\Job\Entity\SchedulerExecutionLog;
use App\Job\Message\RefreshJobSourcesMessage;
use App\Job\Repository\SchedulerExecutionLogRepository;
use Cron\CronExpression;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SchedulerInspector
{
    public function __construct(
        private SchedulerExecutionLogRepository $executionLogRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return list<array{
     *     name: string,
     *     title: string,
     *     description: string,
     *     cronExpression: string,
     *     cronHuman: string,
     *     timezone: string,
     *     messageClass: string,
     *     nextRun: ?\DateTimeImmutable,
     *     lastRun: ?SchedulerExecutionLog,
     * }>
     */
    public function getConfiguredSchedules(): array
    {
        $schedules = [
            [
                'name' => 'job_sync',
                'title' => "Synchronisation quotidienne des offres d'emploi",
                'description' => 'Interroge les sources actives, détecte les nouvelles offres, met à jour les offres modifiées et marque les offres expirées pour tous les profils.',
                'cronExpression' => '0 4 * * *',
                'cronHuman' => 'Tous les jours à 04:00 (Europe/Paris)',
                'timezone' => 'Europe/Paris',
                'messageClass' => RefreshJobSourcesMessage::class,
            ],
        ];

        $result = [];
        foreach ($schedules as $schedule) {
            $nextRun = null;
            try {
                $cron = new CronExpression($schedule['cronExpression']);
                $nextRunDateTime = $cron->getNextRunDate('now', 0, false, $schedule['timezone']);
                $nextRun = \DateTimeImmutable::createFromMutable($nextRunDateTime);
            } catch (\Throwable) {
                // If calculation fails, keep nextRun as null
            }

            $lastRun = $this->executionLogRepository->findLastForSchedule($schedule['name']);

            $result[] = [
                'name' => $schedule['name'],
                'title' => $schedule['title'],
                'description' => $schedule['description'],
                'cronExpression' => $schedule['cronExpression'],
                'cronHuman' => $schedule['cronHuman'],
                'timezone' => $schedule['timezone'],
                'messageClass' => $schedule['messageClass'],
                'nextRun' => $nextRun,
                'lastRun' => $lastRun,
            ];
        }

        return $result;
    }

    public function triggerSchedule(string $scheduleName, string $triggeredBy = 'admin'): void
    {
        if ($scheduleName === 'job_sync') {
            $this->messageBus->dispatch(new RefreshJobSourcesMessage(triggeredBy: $triggeredBy));

            return;
        }

        throw new \InvalidArgumentException(sprintf('Unknown schedule: %s', $scheduleName));
    }
}
