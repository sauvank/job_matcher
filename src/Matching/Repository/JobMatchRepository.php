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
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('CASE WHEN jobMatch.semanticScore IS NULL THEN 1 ELSE 0 END AS HIDDEN semanticScoreMissing')
            ->addSelect('jobOffer', 'jobSource')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobOffer.status != :expiredStatus')
            ->andWhere('(jobOffer.validThrough IS NULL OR jobOffer.validThrough >= :today)')
            ->setParameter('profile', $profile)
            ->setParameter('expiredStatus', JobOfferStatus::EXPIRED)
            ->setParameter('today', $today)
            ->orderBy('semanticScoreMissing', 'ASC')
            ->addOrderBy('jobMatch.semanticScore', 'DESC')
            ->addOrderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);
        $this->restrictToPreferredContractTypes($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findLatestForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('jobOffer', 'jobSource')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobOffer.status != :expiredStatus')
            ->andWhere('(jobOffer.validThrough IS NULL OR jobOffer.validThrough >= :today)')
            ->setParameter('profile', $profile)
            ->setParameter('expiredStatus', JobOfferStatus::EXPIRED)
            ->setParameter('today', $today)
            ->orderBy('jobOffer.firstSeenAt', 'DESC')
            ->addOrderBy('jobOffer.publishedAt', 'DESC')
            ->addOrderBy('jobMatch.id', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);
        $this->restrictToPreferredContractTypes($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findCompletedForProfile(CandidateProfile $profile, int $limit = 100): array
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('jobOffer', 'jobSource')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobMatch.semanticAnalysisStatus = :status')
            ->andWhere('jobOffer.status != :expiredStatus')
            ->andWhere('(jobOffer.validThrough IS NULL OR jobOffer.validThrough >= :today)')
            ->setParameter('profile', $profile)
            ->setParameter('status', SemanticAnalysisStatus::COMPLETED)
            ->setParameter('expiredStatus', JobOfferStatus::EXPIRED)
            ->setParameter('today', $today)
            ->orderBy('jobMatch.semanticAnalyzedAt', 'DESC')
            ->setMaxResults($limit);
        $this->restrictToActiveCv($queryBuilder, $profile);
        $this->restrictToPreferredContractTypes($queryBuilder, $profile);

        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findMatchesForDailyAlert(
        CandidateProfile $profile,
        int $minScore,
        \DateTimeImmutable $since,
        int $limit = 20,
        bool $force = false,
    ): array {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('CASE WHEN jobMatch.semanticScore IS NOT NULL THEN jobMatch.semanticScore ELSE jobMatch.globalScore END AS HIDDEN effectiveScore')
            ->addSelect('jobOffer', 'jobSource')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobMatch.applicationStatus != :notInterested')
            ->andWhere('jobOffer.status != :expired')
            ->andWhere('(jobOffer.validThrough IS NULL OR jobOffer.validThrough >= :today)')
            ->andWhere('(jobMatch.semanticScore >= :minScore OR (jobMatch.semanticScore IS NULL AND jobMatch.globalScore >= :minScore))');

        if (!$force) {
            $queryBuilder->andWhere('jobMatch.alertSentAt IS NULL');
        }

        $queryBuilder->andWhere('(jobOffer.firstSeenAt >= :since OR jobMatch.analyzedAt >= :since OR (jobMatch.semanticAnalyzedAt IS NOT NULL AND jobMatch.semanticAnalyzedAt >= :since) OR (jobOffer.publishedAt IS NOT NULL AND jobOffer.publishedAt >= :since))')
            ->setParameter('profile', $profile)
            ->setParameter('notInterested', JobApplicationStatus::NOT_INTERESTED)
            ->setParameter('expired', JobOfferStatus::EXPIRED)
            ->setParameter('today', $today)
            ->setParameter('minScore', $minScore)
            ->setParameter('since', $since)
            ->orderBy('effectiveScore', 'DESC')
            ->addOrderBy('jobOffer.firstSeenAt', 'DESC')
            ->setMaxResults($limit);

        $this->restrictToActiveCv($queryBuilder, $profile);
        $this->restrictToPreferredContractTypes($queryBuilder, $profile);

        /* @var list<JobMatch> */
        return $queryBuilder->getQuery()->getResult();
    }

    /** @return list<JobMatch> */
    public function findForKanban(CandidateProfile $profile, int $limit = 300): array
    {
        $today = (new \DateTimeImmutable('today'))->setTime(0, 0);

        $queryBuilder = $this->createQueryBuilder('jobMatch')
            ->addSelect('CASE WHEN jobMatch.semanticScore IS NULL THEN 1 ELSE 0 END AS HIDDEN semanticScoreMissing')
            ->addSelect('jobOffer', 'jobSource')
            ->innerJoin('jobMatch.jobOffer', 'jobOffer')
            ->innerJoin('jobOffer.source', 'jobSource')
            ->andWhere('jobMatch.candidateProfile = :profile')
            ->andWhere('jobOffer.status != :expiredStatus')
            ->andWhere('(jobOffer.validThrough IS NULL OR jobOffer.validThrough >= :today)')
            ->setParameter('profile', $profile)
            ->setParameter('expiredStatus', JobOfferStatus::EXPIRED)
            ->setParameter('today', $today)
            ->orderBy('jobMatch.statusUpdatedAt', 'DESC')
            ->addOrderBy('semanticScoreMissing', 'ASC')
            ->addOrderBy('jobMatch.semanticScore', 'DESC')
            ->addOrderBy('jobMatch.globalScore', 'DESC')
            ->setMaxResults($limit);

        $this->restrictToActiveCv($queryBuilder, $profile);
        $this->restrictToPreferredContractTypes($queryBuilder, $profile);

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

    private function restrictToPreferredContractTypes(QueryBuilder $queryBuilder, CandidateProfile $profile): void
    {
        $preferredContractTypes = array_values(array_filter(
            $profile->getPreferredContractTypes(),
            static fn (string $type): bool => trim($type) !== ''
        ));

        if ($preferredContractTypes === []) {
            return;
        }

        $orConditions = [];
        $paramIndex = 0;

        foreach ($preferredContractTypes as $preference) {
            $pref = mb_strtoupper(trim($preference));
            if ($pref === 'CDI') {
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%CDI%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%FULL_TIME%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%PERMANENT%'";
            } elseif ($pref === 'CDD') {
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%CDD%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%TEMPORARY%'";
            } elseif ($pref === 'FREELANCE') {
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%FREELANCE%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%INDEPENDANT%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%INDÉPENDANT%'";
            } elseif ($pref === 'APPRENTICESHIP' || $pref === 'ALTERNANCE') {
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%APPRENT%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%ALTERN%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%CONTRAT PRO%'";
            } elseif ($pref === 'INTERNSHIP' || $pref === 'STAGE') {
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%STAGE%'";
                $orConditions[] = "UPPER(jobOffer.contractType) LIKE '%INTERN%'";
            } else {
                $paramName = 'prefContract_'.$paramIndex++;
                $orConditions[] = 'UPPER(jobOffer.contractType) LIKE :'.$paramName;
                $queryBuilder->setParameter($paramName, '%'.$pref.'%');
            }
        }

        $queryBuilder->andWhere('('.implode(' OR ', $orConditions).')');
    }
}
