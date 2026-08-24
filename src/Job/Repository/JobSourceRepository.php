<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
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
        $queryBuilder = $this->createQueryBuilder('source')
            ->andWhere('source.candidateProfile = :profile')
            ->andWhere('source.provider != :manualProvider')
            ->setParameter('profile', $profile)
            ->setParameter('manualProvider', JobProviderType::MANUAL)
            ->orderBy('source.createdAt', 'DESC');

        $activeCvDocument = $profile->getActiveCvDocument();
        if ($activeCvDocument === null) {
            $queryBuilder->andWhere('source.cvDocument IS NULL');
        } else {
            $queryBuilder->andWhere('source.cvDocument = :activeCvDocument')
                ->setParameter('activeCvDocument', $activeCvDocument);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
