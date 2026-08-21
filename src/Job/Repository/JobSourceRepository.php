<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Job\Entity\JobSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<JobSource> */
final class JobSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JobSource::class);
    }
}
