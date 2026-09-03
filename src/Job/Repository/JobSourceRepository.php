<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobSourceSyncStatus;
use App\Job\Translation\JobMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobSource> */
final class JobSourceRepository extends ServiceEntityRepository implements JobSourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobSource::class);
    }

    public function get(int $id): ?JobSource
    {
        return $this->find($id);
    }

    public function findOneByProfileAndUrl(CandidateProfile $profile, string $url): ?JobSource
    {
        return $this->findOneBy([
            'candidateProfile' => $profile,
            'cvDocument' => $profile->getActiveCvDocument(),
            'url' => $url,
        ]);
    }

    /** @return list<JobSource> */
    public function findEnabled(): array
    {
        return $this->createQueryBuilder('source')
            ->innerJoin('source.candidateProfile', 'profile')
            ->andWhere('source.enabled = true')
            ->andWhere('(source.cvDocument = profile.activeCvDocument) OR (source.cvDocument IS NULL AND profile.activeCvDocument IS NULL)')
            ->orderBy('source.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<JobSource> */
    public function findForProfile(CandidateProfile $profile): array
    {
        return $this->findBy([
            'candidateProfile' => $profile,
            'cvDocument' => $profile->getActiveCvDocument(),
        ], ['createdAt' => 'DESC']);
    }

    public function recoverStuckSyncsForProfile(CandidateProfile $profile, int $timeoutMinutes = 10): int
    {
        $now = new \DateTimeImmutable();
        $cutoff = $now->modify("-{$timeoutMinutes} minutes");

        $stuckSources = $this->createQueryBuilder('source')
            ->andWhere('source.candidateProfile = :profile')
            ->andWhere('source.syncStatus IN (:pendingStatuses)')
            ->andWhere('(source.lastSyncStartedAt IS NULL OR source.lastSyncStartedAt < :cutoff)')
            ->setParameter('profile', $profile)
            ->setParameter('pendingStatuses', [JobSourceSyncStatus::QUEUED, JobSourceSyncStatus::RUNNING])
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($stuckSources as $source) {
            $source->failSync(JobMessage::SYNC_FAILED);
            ++$count;
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }

    public function recoverAllStuckSyncs(int $timeoutMinutes = 10): int
    {
        $now = new \DateTimeImmutable();
        $cutoff = $now->modify("-{$timeoutMinutes} minutes");

        $stuckSources = $this->createQueryBuilder('source')
            ->andWhere('source.syncStatus IN (:pendingStatuses)')
            ->andWhere('(source.lastSyncStartedAt IS NULL OR source.lastSyncStartedAt < :cutoff)')
            ->setParameter('pendingStatuses', [JobSourceSyncStatus::QUEUED, JobSourceSyncStatus::RUNNING])
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($stuckSources as $source) {
            $source->failSync(JobMessage::SYNC_FAILED);
            ++$count;
        }

        if ($count > 0) {
            $this->getEntityManager()->flush();
        }

        return $count;
    }
}
