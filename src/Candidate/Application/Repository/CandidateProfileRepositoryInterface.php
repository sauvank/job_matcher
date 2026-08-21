<?php

declare(strict_types=1);

namespace App\Candidate\Application\Repository;

use App\Candidate\Entity\CandidateProfile;

interface CandidateProfileRepositoryInterface
{
    public function findDefault(): ?CandidateProfile;
}
