<?php

declare(strict_types=1);

namespace App\Matching\DTO;

use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;
use App\Matching\Translation\MatchingMessage;

final readonly class AnalyzedRequirement
{
    public function __construct(
        public RequirementCategory $category,
        public RequirementImportance $importance,
        public string $label,
        public string $offerEvidence,
        public RequirementAssessment $assessment,
        public ?string $cvEvidence,
        public string $explanation,
    ) {
        if (trim($label) === '' || trim($offerEvidence) === '' || trim($explanation) === '') {
            throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['category', 'importance', 'label', 'offerEvidence', 'assessment', 'explanation'] as $key) {
            if (!is_string($data[$key] ?? null)) {
                throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
            }
        }
        $cvEvidence = $data['cvEvidence'] ?? null;
        if ($cvEvidence !== null && !is_string($cvEvidence)) {
            throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
        }

        return new self(
            RequirementCategory::from($data['category']),
            RequirementImportance::from($data['importance']),
            trim($data['label']),
            trim($data['offerEvidence']),
            RequirementAssessment::from($data['assessment']),
            is_string($cvEvidence) && trim($cvEvidence) !== '' ? trim($cvEvidence) : null,
            trim($data['explanation']),
        );
    }

    /** @return array{category: string, importance: string, label: string, offerEvidence: string, assessment: string, cvEvidence: string|null, explanation: string} */
    public function toArray(): array
    {
        return [
            'category' => $this->category->value,
            'importance' => $this->importance->value,
            'label' => $this->label,
            'offerEvidence' => $this->offerEvidence,
            'assessment' => $this->assessment->value,
            'cvEvidence' => $this->cvEvidence,
            'explanation' => $this->explanation,
        ];
    }
}
