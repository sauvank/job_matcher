<?php

declare(strict_types=1);

namespace App\Matching\Application\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\DTO\SemanticJobAnalysis;

interface JobSemanticAnalyzerInterface
{
    public function analyze(CandidateProfile $profile, JobOffer $offer): SemanticJobAnalysis;

    public function name(): string;
}
