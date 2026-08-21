<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobOffer> */
final class JobOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobOffer::class);
    }

    public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer
    {
        return $this->findOneBy(['source' => $source, 'externalId' => $externalId]);
    }

    /** @return list<JobOffer> */
    public function findRecent(int $limit = 100): array
    {
        return $this->findBy([], ['publishedAt' => 'DESC', 'firstSeenAt' => 'DESC'], $limit);
    }
}
