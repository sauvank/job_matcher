<?php

declare(strict_types=1);

namespace App\Matching\DTO;

use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;
use App\Matching\Translation\MatchingMessage;

final readonly class SemanticJobAnalysis
{
    /**
     * @param list<AnalyzedRequirement> $requirements
     * @param list<string>              $strengths
     * @param list<string>              $concerns
     * @param list<string>              $questions
     * @param list<string>              $keyExpectations
     * @param list<string>              $requiredCapacities
     */
    public function __construct(
        public int $compatibilityScore,
        public string $summary,
        public array $requirements,
        public array $strengths,
        public array $concerns,
        public array $questions,
        public ?string $jobSummary = null,
        public array $keyExpectations = [],
        public array $requiredCapacities = [],
    ) {
        if ($compatibilityScore < 0 || $compatibilityScore > 100 || trim($summary) === '' || $requirements === []) {
            throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $summary = $data['summary'] ?? null;
        $compatibilityScore = $data['compatibilityScore'] ?? null;
        $requirementsData = $data['requirements'] ?? null;
        if (!is_int($compatibilityScore) || !is_string($summary) || !is_array($requirementsData)) {
            throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
        }

        $requirements = [];
        foreach ($requirementsData as $requirement) {
            if (!is_array($requirement)) {
                throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
            }
            /* @var array<string, mixed> $requirement */
            $requirements[] = AnalyzedRequirement::fromArray($requirement);
        }

        $jobSummary = isset($data['jobSummary']) && is_string($data['jobSummary']) && trim($data['jobSummary']) !== ''
            ? trim($data['jobSummary'])
            : null;

        $keyExpectations = self::optionalStringList($data['keyExpectations'] ?? null);
        if ($keyExpectations === []) {
            foreach ($requirements as $req) {
                if ($req->category === RequirementCategory::RESPONSIBILITY && count($keyExpectations) < 4) {
                    $keyExpectations[] = $req->label;
                }
            }
        }

        $requiredCapacities = self::optionalStringList($data['requiredCapacities'] ?? null);
        if ($requiredCapacities === []) {
            foreach ($requirements as $req) {
                if ($req->importance === RequirementImportance::REQUIRED
                    && in_array($req->category, [RequirementCategory::TECHNICAL, RequirementCategory::EXPERIENCE, RequirementCategory::SOFT_SKILL, RequirementCategory::CERTIFICATION], true)
                    && count($requiredCapacities) < 5
                ) {
                    $requiredCapacities[] = $req->label;
                }
            }
        }

        return new self(
            $compatibilityScore,
            trim($summary),
            $requirements,
            self::stringList($data['strengths'] ?? null),
            self::stringList($data['concerns'] ?? null),
            self::stringList($data['questions'] ?? null),
            $jobSummary,
            $keyExpectations,
            $requiredCapacities,
        );
    }

    /** @return array{compatibilityScore: int, summary: string, jobSummary: string|null, keyExpectations: list<string>, requiredCapacities: list<string>, requirements: list<array{category: string, importance: string, label: string, offerEvidence: string, assessment: string, cvEvidence: string|null, explanation: string}>, strengths: list<string>, concerns: list<string>, questions: list<string>} */
    public function toArray(): array
    {
        return [
            'compatibilityScore' => $this->compatibilityScore,
            'summary' => $this->summary,
            'jobSummary' => $this->jobSummary,
            'keyExpectations' => $this->keyExpectations,
            'requiredCapacities' => $this->requiredCapacities,
            'requirements' => array_map(static fn (AnalyzedRequirement $requirement): array => $requirement->toArray(), $this->requirements),
            'strengths' => $this->strengths,
            'concerns' => $this->concerns,
            'questions' => $this->questions,
        ];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new \InvalidArgumentException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS);
            }
            $items[] = trim($item);
        }

        return $items;
    }

    /** @return list<string> */
    private static function optionalStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $items[] = trim($item);
            }
        }

        return $items;
    }
}
