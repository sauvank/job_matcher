<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Persistence;

use App\Candidate\Entity\Skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Skill> */
final class SkillRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Skill::class);
    }

    public function findOneByNormalizedName(string $normalizedName): ?Skill
    {
        return $this->findOneBy(['normalizedName' => $normalizedName]);
    }
}
