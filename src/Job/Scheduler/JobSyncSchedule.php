<?php

declare(strict_types=1);

namespace App\Job\Scheduler;

use App\Job\Message\RefreshJobSourcesMessage;
use App\Job\Message\SendDailyJobAlertsMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('job_sync')]
final readonly class JobSyncSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::cron('0 4 * * *', new RefreshJobSourcesMessage(), new \DateTimeZone('Europe/Paris')))
            ->add(RecurringMessage::cron('0 8 * * *', new SendDailyJobAlertsMessage(), new \DateTimeZone('Europe/Paris')))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->lock($this->lockFactory->createLock('scheduler-job-sync'));
    }
}
