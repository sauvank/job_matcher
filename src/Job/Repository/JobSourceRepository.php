<?php

declare(strict_types=1);

namespace App\Job\Repository;

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

    public function findOneByProvider(JobProviderType $provider): ?JobSource
    {
        return $this->findOneBy(['provider' => $provider]);
    }
}
