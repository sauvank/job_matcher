<?php

declare(strict_types=1);

namespace App\Matching\Application\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Entity\JobMatch;

interface JobMatchRepositoryInterface
{
    public function findOneFor(CandidateProfile $profile, JobOffer $offer): ?JobMatch;
}
