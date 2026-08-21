<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Matching\DTO\AnalyzedRequirement;
use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;

final class CvOptimizationAccumulator
{
    /** @var array<int, true> */
    private array $offerIds = [];

    /** @var array<int, true> */
    private array $requiredOfferIds = [];

    /** @var array<string, true> */
    private array $assessments = [];

    private ?string $cvEvidence = null;

    public function __construct(
        public readonly string $label,
        public readonly RequirementCategory $category,
        public readonly int $exampleOfferId,
        public readonly string $exampleOfferTitle,
    ) {
    }

    public function add(AnalyzedRequirement $requirement, int $offerId): void
    {
        $this->offerIds[$offerId] = true;
        if ($requirement->importance === RequirementImportance::REQUIRED) {
            $this->requiredOfferIds[$offerId] = true;
        }
        $this->assessments[$requirement->assessment->value] = true;
        $this->cvEvidence ??= $requirement->cvEvidence;
    }

    public function offerCount(): int
    {
        return count($this->offerIds);
    }

    public function requiredCount(): int
    {
        return count($this->requiredOfferIds);
    }

    public function cvEvidence(): ?string
    {
        return $this->cvEvidence;
    }

    public function has(RequirementAssessment $assessment): bool
    {
        return isset($this->assessments[$assessment->value]);
    }
}
