<?php

declare(strict_types=1);

namespace App\Candidate\Application\Repository;

use App\Candidate\Entity\CandidateProfile;

interface CandidateProfileRepositoryInterface
{
    /** @return list<CandidateProfile> */
    public function findAll(): array;
}
