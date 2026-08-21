<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Candidate\Translation\CandidateMessage;

final readonly class AnalyzedSkill
{
    public function __construct(
        public string $name,
        public SkillCategory $category,
        public ?SkillLevel $level,
        public ?int $yearsOfExperience,
        public bool $isCoreSkill,
        public float $confidence,
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_SKILL_NAME);
        }

        if ($confidence < 0 || $confidence > 1) {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_CONFIDENCE);
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $category = $data['category'] ?? null;
        $level = $data['level'] ?? null;
        $years = $data['yearsOfExperience'] ?? null;
        $isCoreSkill = $data['isCoreSkill'] ?? null;
        $confidence = $data['confidence'] ?? null;

        if (!is_string($name) || !is_string($category) || (!is_string($level) && $level !== null)) {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_SKILL);
        }

        if ((!is_int($years) && $years !== null) || !is_bool($isCoreSkill) || (!is_float($confidence) && !is_int($confidence))) {
            throw new \InvalidArgumentException(CandidateMessage::INVALID_SKILL_METADATA);
        }

        return new self(
            trim($name),
            SkillCategory::from($category),
            $level === null ? null : SkillLevel::from($level),
            $years,
            $isCoreSkill,
            (float) $confidence,
        );
    }

    /** @return array{name: string, category: string, level: string|null, yearsOfExperience: int|null, isCoreSkill: bool, confidence: float} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category->value,
            'level' => $this->level?->value,
            'yearsOfExperience' => $this->yearsOfExperience,
            'isCoreSkill' => $this->isCoreSkill,
            'confidence' => $this->confidence,
        ];
    }
}
