<?php

declare(strict_types=1);

namespace App\Candidate\Entity;

use App\Candidate\Enum\SkillLevel;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'candidate_skill')]
#[ORM\UniqueConstraint(name: 'uniq_candidate_skill', columns: ['candidate_profile_id', 'skill_id'])]
final class CandidateSkill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'candidateSkills')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CandidateProfile $candidateProfile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Skill $skill;

    #[ORM\Column(enumType: SkillLevel::class, nullable: true)]
    private ?SkillLevel $level;

    #[ORM\Column(nullable: true)]
    private ?int $yearsOfExperience;

    #[ORM\Column]
    private bool $isCoreSkill;

    #[ORM\Column(nullable: true)]
    private ?float $confidence;

    public function __construct(
        CandidateProfile $candidateProfile,
        Skill $skill,
        ?SkillLevel $level = null,
        ?int $yearsOfExperience = null,
        bool $isCoreSkill = false,
        ?float $confidence = null,
    ) {
        $this->candidateProfile = $candidateProfile;
        $this->skill = $skill;
        $this->level = $level;
        $this->yearsOfExperience = $yearsOfExperience;
        $this->isCoreSkill = $isCoreSkill;
        $this->confidence = $confidence;
        $candidateProfile->addCandidateSkill($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
    }

    public function getSkill(): Skill
    {
        return $this->skill;
    }

    public function getLevel(): ?SkillLevel
    {
        return $this->level;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->yearsOfExperience;
    }

    public function isCoreSkill(): bool
    {
        return $this->isCoreSkill;
    }

    public function getConfidence(): ?float
    {
        return $this->confidence;
    }

    public function updateFromAnalysis(?SkillLevel $level, ?int $yearsOfExperience, bool $isCoreSkill, float $confidence): void
    {
        $this->level = $level;
        $this->yearsOfExperience = $yearsOfExperience;
        $this->isCoreSkill = $isCoreSkill;
        $this->confidence = $confidence;
    }

    public function updateLevel(SkillLevel $level): void
    {
        $this->level = $level;
    }
}
