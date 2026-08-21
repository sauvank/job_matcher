<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Persistence;

use App\Candidate\Entity\CandidateProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CandidateProfile> */
final class CandidateProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CandidateProfile::class);
    }

    public function findDefault(): ?CandidateProfile
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
