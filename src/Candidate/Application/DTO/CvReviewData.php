<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Entity\CvDocument;
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

    public static function fromDocument(CvDocument $document, CvAnalysisResult $analysis): self
    {
        $selectedSkills = [];
        $appliedSkills = $document->getCandidateProfile()->getCandidateSkillsFor($document);
        if ($appliedSkills->count() > 0) {
            foreach ($appliedSkills as $candidateSkill) {
                $selectedSkills[] = $candidateSkill->getSkill()->getName();
            }
        } else {
            $selectedSkills = array_map(static fn (AnalyzedSkill $skill): string => $skill->name, $analysis->skills);
        }

        return new self(
            $document->getAppliedTitle() ?? $analysis->suggestedTitle,
            $document->getAppliedLocation() ?? $analysis->location,
            $document->getAppliedYearsOfExperience() ?? $analysis->yearsOfExperience,
            $selectedSkills,
        );
    }
}
