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

    /** @return list<JobMatch> */
    public function findMatchesForDailyAlert(CandidateProfile $profile, int $minScore, \DateTimeImmutable $since, int $limit = 20, bool $force = false): array;

    /** @return list<JobMatch> */
    public function findForKanban(CandidateProfile $profile, int $limit = 300): array;

    /**
     * @param list<int> $ids
     *
     * @return list<JobMatch>
     */
    public function findPendingSemanticAnalyses(array $ids = []): array;

    public function recoverStuckAnalysesForProfile(CandidateProfile $profile, int $timeoutMinutes = 10): int;

    public function recoverAllStuckAnalyses(int $timeoutMinutes = 10): int;
}
