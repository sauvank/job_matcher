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

    public function testItCanUpdateDetailsAndSynchronizeActiveCv(): void
    {
        $profile = new CandidateProfile();
        $cv = $this->appliedDocument($profile, 'dev.pdf', 'Développeur Junior', 'Paris', 1, ['CDI']);
        $profile->activateCvDocument($cv);

        self::assertSame('Paris', $profile->getLocation());
        self::assertSame('Paris', $cv->getAppliedLocation());
        self::assertSame(['CDI'], $profile->getPreferredContractTypes());
        self::assertSame(['CDI'], $cv->getAppliedContractTypes());

        $profile->updateDetails('  Lead Developer  ', '  Lyon et périphérie  ', 7, ['FREELANCE', 'CDI'], 60000, 600);

        self::assertSame('Lead Developer', $profile->getTitle());
        self::assertSame('Lyon et périphérie', $profile->getLocation());
        self::assertSame(7, $profile->getYearsOfExperience());
        self::assertSame(['FREELANCE', 'CDI'], $profile->getPreferredContractTypes());
        self::assertSame(60000, $profile->getMinimumSalary());
        self::assertSame(600, $profile->getMinimumDailyRate());
        self::assertSame('Lead Developer', $cv->getAppliedTitle());
        self::assertSame('Lyon et périphérie', $cv->getAppliedLocation());
        self::assertSame(7, $cv->getAppliedYearsOfExperience());
        self::assertSame(['FREELANCE', 'CDI'], $cv->getAppliedContractTypes());

        $profile->updateDetails('', '   ', null, [], null, null);
        self::assertNull($profile->getTitle());
        self::assertNull($profile->getLocation());
        self::assertNull($profile->getYearsOfExperience());
        self::assertSame([], $profile->getPreferredContractTypes());
        self::assertNull($profile->getMinimumSalary());
        self::assertNull($profile->getMinimumDailyRate());
        self::assertNull($cv->getAppliedTitle());
        self::assertNull($cv->getAppliedLocation());
        self::assertNull($cv->getAppliedYearsOfExperience());
        self::assertSame([], $cv->getAppliedContractTypes());
    }

    /** @param list<string> $contractTypes */
    private function appliedDocument(
        CandidateProfile $profile,
        string $filename,
        string $title,
        string $location,
        int $yearsOfExperience,
        array $contractTypes = [],
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
        $document->markApplied($title, $location, $yearsOfExperience, $contractTypes);

        return $document;
    }
}
