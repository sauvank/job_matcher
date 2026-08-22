<?php

declare(strict_types=1);

namespace App\Job\Application\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobSource;

interface JobSourceRepositoryInterface
{
    public function get(int $id): ?JobSource;

    public function findOneByProfileAndUrl(CandidateProfile $profile, string $url): ?JobSource;
}
