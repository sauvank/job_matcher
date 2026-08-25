<?php

declare(strict_types=1);

namespace App\Matching\Application\Repository;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Entity\JobMatch;

interface JobMatchRepositoryInterface
{
    public function get(int $id): ?JobMatch;

    public function findOneFor(CandidateProfile $profile, JobOffer $offer): ?JobMatch;

    /** @return list<JobMatch> */
    public function findRankedForProfile(CandidateProfile $profile, int $limit = 100): array;

    /** @return list<JobMatch> */
    public function findLatestForProfile(CandidateProfile $profile, int $limit = 100): array;

    /** @return list<JobMatch> */
    public function findCompletedForProfile(CandidateProfile $profile, int $limit = 100): array;
}
