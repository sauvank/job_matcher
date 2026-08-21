<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobOffer> */
final class JobOfferRepository extends ServiceEntityRepository implements JobOfferRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    public function deleteBySource(JobSource $source): void
    {
        $this->createQueryBuilder('offer')
            ->delete()
            ->where('offer.source = :source')
            ->setParameter('source', $source)
            ->getQuery()
            ->execute();
    }

    /** @return list<JobOffer> */
    public function findRecent(int $limit = 100): array
    {
        return $this->findBy([], ['publishedAt' => 'DESC', 'firstSeenAt' => 'DESC'], $limit);
    }
}
