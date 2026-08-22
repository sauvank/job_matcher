<?php

declare(strict_types=1);

namespace App\Job\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
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
        return $this->findOneBy(['candidateProfile' => $profile, 'url' => $url]);
    }

    /** @return list<JobSource> */
    public function findForProfile(CandidateProfile $profile): array
    {
        return $this->findBy(['candidateProfile' => $profile], ['createdAt' => 'DESC']);
    }
}
