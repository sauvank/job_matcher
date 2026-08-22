<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use PHPUnit\Framework\TestCase;

final class CandidateProfileTest extends TestCase
{
    public function testItRetainsOnlySkillsSelectedFromTheActiveCv(): void
    {
        $profile = new CandidateProfile();
        new CandidateSkill($profile, new Skill('PHP', 'php', SkillCategory::BACKEND));
        new CandidateSkill($profile, new Skill('React', 'react', SkillCategory::FRONTEND));

        $profile->retainCandidateSkills(['php']);

        self::assertCount(1, $profile->getCandidateSkills());
        $remainingSkill = $profile->getCandidateSkills()->first();
        self::assertInstanceOf(CandidateSkill::class, $remainingSkill);
        self::assertSame('php', $remainingSkill->getSkill()->getNormalizedName());
    }

    public function testItCanUpdateAndRemoveAManualSkill(): void
    {
        $profile = new CandidateProfile();
        $candidateSkill = new CandidateSkill($profile, new Skill('PHP', 'php', SkillCategory::BACKEND));

        $candidateSkill->updateLevel(SkillLevel::EXPERT);
        self::assertSame(SkillLevel::EXPERT, $candidateSkill->getLevel());

        $profile->removeCandidateSkill($candidateSkill);
        self::assertCount(0, $profile->getCandidateSkills());
    }
}
