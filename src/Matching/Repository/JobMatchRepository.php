<?php

declare(strict_types=1);

namespace App\Matching\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Job\Enum\JobOfferStatus;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Enum\SemanticAnalysisStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobMatch> */
final class JobMatchRepository extends ServiceEntityRepository implements JobMatchRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobMatch::class);
    }

    public function findOneFor(CandidateProfile $profile, JobOffer $offer): ?JobMatch
    {
        return $this->findOneBy(['candidateProfile' => $profile, 'jobOffer' => $offer]);
    }

    public function get(int $id): ?JobMatch
    {
        return $this->find($id);
    }

    /** @return list<JobMatch> */
    public function findRankedForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('CASE WHEN jobMatch.semanticScore IS NULL THEN 1 ELSE 0 END AS HIDDEN semanticScoreMissing')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('semanticScoreMissing', 'ASC')
            ->addOrderBy('jobMatch.semanticScore', 'DESC')
            ->addOrderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findLatestForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('jobOffer.firstSeenAt', 'DESC')
            ->addOrderBy('jobOffer.publishedAt', 'DESC')
            ->addOrderBy('jobMatch.id', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findCompletedForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('jobOffer')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobMatch.semanticAnalysisStatus = :status')
            ->setParameter('profile', $profile)
            ->setParameter('status', SemanticAnalysisStatus::COMPLETED)
            ->orderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findMatchesForDailyAlert(
        CandidateProfile $profile,
        int $minScore,
        \DateTimeImmutable $since,
        int $limit = 20,
    ): array {
        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobMatch.applicationStatus != :notInterested')
            ->andWhere('jobOffer.status != :expired')
            ->andWhere('COALESCE(jobMatch.semanticScore, jobMatch.globalScore) >= :minScore')
            ->andWhere('(jobOffer.firstSeenAt >= :since OR jobMatch.analyzedAt >= :since OR (jobMatch.semanticAnalyzedAt IS NOT NULL AND jobMatch.semanticAnalyzedAt >= :since) OR (jobOffer.publishedAt IS NOT NULL AND jobOffer.publishedAt >= :since))')
            ->setParameter('profile', $profile)
            ->setParameter('notInterested', JobApplicationStatus::NOT_INTERESTED)
            ->setParameter('expired', JobOfferStatus::EXPIRED)
            ->setParameter('minScore', $minScore)
            ->setParameter('since', $since)
            ->orderBy('COALESCE(jobMatch.semanticScore, jobMatch.globalScore)', 'DESC')
            ->addOrderBy('jobOffer.firstSeenAt', 'DESC')
            ->setMaxResults($limit);

        $this->restrictToActiveCv($queryBuilder, $profile);

        /* @var list<JobMatch> */
        return $queryBuilder->getQuery()->getResult();
    }

    private function restrictToActiveCv(QueryBuilder $queryBuilder, CandidateProfile $profile): void
    {
        $activeCvDocument = $profile->getActiveCvDocument();
        if ($activeCvDocument === null) {
            $queryBuilder->andWhere('jobSource.cvDocument IS NULL');

            return;
        }

        $queryBuilder->andWhere('jobSource.cvDocument = :activeCv')
            ->setParameter('activeCv', $activeCvDocument);
    }
}
