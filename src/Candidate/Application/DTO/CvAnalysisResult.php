<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Translation\CandidateMessage;

final readonly class CvAnalysisResult
{
    /**
     * @param list<AnalyzedSkill> $skills
     * @param list<string>        $warnings
     */
    public function __construct(
        public ?string $suggestedTitle,
        public ?string $location,
        public ?int $yearsOfExperience,
        public array $skills,
        public string $summary,
        public array $warnings,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $title = self::nullableString($data['suggestedTitle'] ?? null);
        $location = self::nullableString($data['location'] ?? null);
        $years = $data['yearsOfExperience'] ?? null;
        $summary = $data['summary'] ?? null;
        $skillsData = $data['skills'] ?? null;
        $warningsData = $data['warnings'] ?? null;

        if ((!is_int($years) && $years !== null) || !is_string($summary) || !is_array($skillsData) || !is_array($warningsData)) {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_ANALYSIS);
        }

        $skills = [];
        foreach ($skillsData as $skillData) {
            if (!is_array($skillData)) {
                throw new \InvalidArgumentException(CandidateMessage::INVALID_SKILL);
            }

            /* @var array<string, mixed> $skillData */
            $skills[] = AnalyzedSkill::fromArray($skillData);
        }

        $warnings = [];
        foreach ($warningsData as $warning) {
            if (!is_string($warning)) {
                throw new \InvalidArgumentException(CandidateMessage::INVALID_WARNING);
            }
            $warnings[] = $warning;
        }

        return new self($title, $location, $years, $skills, trim($summary), $warnings);
    }

    /** @return array{suggestedTitle: string|null, location: string|null, yearsOfExperience: int|null, skills: list<array{name: string, category: string, level: string|null, yearsOfExperience: int|null, isCoreSkill: bool, confidence: float}>, summary: string, warnings: list<string>} */
    public function toArray(): array
    {
        return [
            'suggestedTitle' => $this->suggestedTitle,
            'location' => $this->location,
            'yearsOfExperience' => $this->yearsOfExperience,
            'skills' => array_map(static fn (AnalyzedSkill $skill): array => $skill->toArray(), $this->skills),
            'summary' => $this->summary,
            'warnings' => $this->warnings,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_TEXT);
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
