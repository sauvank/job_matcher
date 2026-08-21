<?php

declare(strict_types=1);

namespace App\Matching\DTO;

use App\Matching\Enum\RequirementCategory;

final readonly class CvOptimizationItem
{
    public function __construct(
        public string $label,
        public RequirementCategory $category,
        public int $offerCount,
        public int $requiredCount,
        public ?string $cvEvidence,
        public string $recommendation,
        public int $exampleOfferId,
        public string $exampleOfferTitle,
        public int $relevanceScore,
    ) {
    }
}
