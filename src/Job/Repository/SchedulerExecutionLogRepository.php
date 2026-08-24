<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Job\Entity\SchedulerExecutionLog;
use App\Job\Enum\SchedulerExecutionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SchedulerExecutionLog> */
final class SchedulerExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchedulerExecutionLog::class);
    }

    /** @return list<SchedulerExecutionLog> */
    public function findLatest(int $limit = 50, ?SchedulerExecutionStatus $status = null, ?string $scheduleName = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $status);
        }

        if ($scheduleName !== null && $scheduleName !== '') {
            $qb->andWhere('l.scheduleName = :scheduleName')
                ->setParameter('scheduleName', $scheduleName);
        }

        /* @var list<SchedulerExecutionLog> */
        return $qb->getQuery()->getResult();
    }

    public function findLastForSchedule(string $scheduleName): ?SchedulerExecutionLog
    {
        return $this->createQueryBuilder('l')
            ->where('l.scheduleName = :scheduleName')
            ->setParameter('scheduleName', $scheduleName)
            ->orderBy('l.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array{total: int, success: int, failed: int, running: int} */
    public function getStatusCounts(): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.status, COUNT(l.id) as cnt')
            ->groupBy('l.status')
            ->getQuery()
            ->getArrayResult();

        $counts = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'running' => 0,
        ];

        foreach ($rows as $row) {
            /** @var SchedulerExecutionStatus|string $status */
            $status = $row['status'];
            $statusVal = $status instanceof SchedulerExecutionStatus ? $status->value : (string) $status;
            $cnt = (int) $row['cnt'];
            $counts['total'] += $cnt;
            if (isset($counts[$statusVal])) {
                $counts[$statusVal] = $cnt;
            }
        }

        return $counts;
    }

    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.startedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
