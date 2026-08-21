<?php

declare(strict_types=1);

namespace App\Matching\DTO;

use App\Matching\Translation\MatchingMessage;

final readonly class SemanticJobAnalysis
{
    /**
     * @param list<AnalyzedRequirement> $requirements
     * @param list<string>              $strengths
     * @param list<string>              $concerns
     * @param list<string>              $questions
     */
    public function __construct(
        public int $compatibilityScore,
        public string $summary,
        public array $requirements,
        public array $strengths,
        public array $concerns,
        public array $questions,
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

        return new self(
            $compatibilityScore,
            trim($summary),
            $requirements,
            self::stringList($data['strengths'] ?? null),
            self::stringList($data['concerns'] ?? null),
            self::stringList($data['questions'] ?? null),
        );
    }

    /** @return array{compatibilityScore: int, summary: string, requirements: list<array{category: string, importance: string, label: string, offerEvidence: string, assessment: string, cvEvidence: string|null, explanation: string}>, strengths: list<string>, concerns: list<string>, questions: list<string>} */
    public function toArray(): array
    {
        return [
            'compatibilityScore' => $this->compatibilityScore,
            'summary' => $this->summary,
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
}
