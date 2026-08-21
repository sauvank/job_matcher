<?php

declare(strict_types=1);

namespace App\Matching\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\SemanticAnalysisStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findRanked(int $limit = 100): array
    {
        return $this->createQueryBuilder('jobMatch')
            ->addSelect('CASE WHEN jobMatch.semanticScore IS NULL THEN 1 ELSE 0 END AS HIDDEN semanticScoreMissing')
            ->orderBy('semanticScoreMissing', 'ASC')
            ->addOrderBy('jobMatch.semanticScore', 'DESC')
            ->addOrderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<JobMatch> */
    public function findCompletedForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        return $this->createQueryBuilder('jobMatch')
            ->addSelect('jobOffer')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobMatch.semanticAnalysisStatus = :status')
            ->setParameter('profile', $profile)
            ->setParameter('status', SemanticAnalysisStatus::COMPLETED)
            ->orderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
