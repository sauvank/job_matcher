<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use PHPUnit\Framework\TestCase;

final class CandidateProfileTest extends TestCase
{
    public function testItRetainsOnlySkillsSelectedFromTheActiveCv(): void
    {
        $profile = new CandidateProfile();
        $phpCv = $this->appliedDocument($profile, 'php.pdf', 'Développeur PHP', 'Paris', 5);
        $reactCv = $this->appliedDocument($profile, 'react.pdf', 'Développeur React', 'Lyon', 3);
        new CandidateSkill($profile, new Skill('PHP', 'php', SkillCategory::BACKEND), cvDocument: $phpCv);
        new CandidateSkill($profile, new Skill('Symfony', 'symfony', SkillCategory::BACKEND), cvDocument: $phpCv);
        new CandidateSkill($profile, new Skill('React', 'react', SkillCategory::FRONTEND), cvDocument: $reactCv);

        $profile->activateCvDocument($phpCv);
        $profile->retainCandidateSkills($phpCv, ['php']);

        self::assertCount(1, $profile->getCandidateSkills());
        $remainingSkill = $profile->getCandidateSkills()->first();
        self::assertInstanceOf(CandidateSkill::class, $remainingSkill);
        self::assertSame('php', $remainingSkill->getSkill()->getNormalizedName());

        $profile->activateCvDocument($reactCv);

        self::assertSame('Développeur React', $profile->getTitle());
        self::assertSame('Lyon', $profile->getLocation());
        self::assertSame(3, $profile->getYearsOfExperience());
        self::assertCount(1, $profile->getCandidateSkills());
        $reactSkill = $profile->getCandidateSkills()->first();
        self::assertInstanceOf(CandidateSkill::class, $reactSkill);
        self::assertSame('react', $reactSkill->getSkill()->getNormalizedName());
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

    private function appliedDocument(
        CandidateProfile $profile,
        string $filename,
        string $title,
        string $location,
        int $yearsOfExperience,
    ): CvDocument {
        $document = new CvDocument(
            $profile,
            $filename,
            'stored-'.$filename,
            'application/pdf',
            1234,
            hash('sha256', $filename),
        );
        $document->markAnalyzing('Texte du '.$filename);
        $document->markApplied($title, $location, $yearsOfExperience);

        return $document;
    }
}
