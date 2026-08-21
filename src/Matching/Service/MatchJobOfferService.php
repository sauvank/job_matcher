<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MatchJobOfferService
{
    public function __construct(
        private JobMatchRepositoryInterface $repository,
        private DeterministicJobScorer $scorer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function match(CandidateProfile $profile, JobOffer $offer): JobMatch
    {
        $score = $this->scorer->score($profile, $offer);
        $match = $offer->getId() === null ? null : $this->repository->findOneFor($profile, $offer);

        if ($match === null) {
            $match = new JobMatch($profile, $offer, $score);
            $this->entityManager->persist($match);
        } else {
            $match->update($score);
        }

        return $match;
    }
}
