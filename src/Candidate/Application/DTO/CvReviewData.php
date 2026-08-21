<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class CvReviewData
{
    /** @param list<string> $selectedSkills */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $title,
        #[Assert\NotBlank]
        #[Assert\Length(max: 160)]
        public ?string $location,
        #[Assert\Range(min: 0, max: 80)]
        public ?int $yearsOfExperience,
        public array $selectedSkills,
    ) {
    }

    public static function fromAnalysis(CvAnalysisResult $analysis): self
    {
        return new self(
            $analysis->suggestedTitle,
            $analysis->location,
            $analysis->yearsOfExperience,
            array_map(static fn (AnalyzedSkill $skill): string => $skill->name, $analysis->skills),
        );
    }
}
