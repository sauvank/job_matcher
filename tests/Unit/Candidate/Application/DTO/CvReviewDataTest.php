<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Application\DTO;

use App\Candidate\Application\DTO\AnalyzedSkill;
use App\Candidate\Application\DTO\CandidateProfileDetailsData;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Application\DTO\CvReviewData;
use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use PHPUnit\Framework\TestCase;

final class CvReviewDataTest extends TestCase
{
    public function testFromAnalysisExtractsDataCorrectly(): void
    {
        $analysis = new CvAnalysisResult(
            suggestedTitle: 'Développeur PHP',
            location: 'Marseille',
            yearsOfExperience: 4,
            skills: [
                new AnalyzedSkill('PHP', SkillCategory::BACKEND, SkillLevel::ADVANCED, 4, true, 0.9),
                new AnalyzedSkill('Docker', SkillCategory::DEVOPS, SkillLevel::INTERMEDIATE, 2, false, 0.8),
            ],
            summary: 'Profil développeur',
            warnings: [],
        );

        $dto = CvReviewData::fromAnalysis($analysis);

        self::assertSame('Développeur PHP', $dto->title);
        self::assertSame('Marseille', $dto->location);
        self::assertSame(4, $dto->yearsOfExperience);
        self::assertSame(['PHP', 'Docker'], $dto->selectedSkills);
        self::assertSame(['CDI'], $dto->contractTypes);
    }

    public function testFromDocumentPrefersAppliedValuesAndAppliedSkills(): void
    {
        $profile = new CandidateProfile();
        $document = new CvDocument(
            $profile,
            'cv.pdf',
            'stored.pdf',
            'application/pdf',
            1024,
            str_repeat('b', 64),
        );
        $document->markAnalyzing('Contenu du CV');
        $document->markApplied('Architecte PHP', 'Toulouse', 10, ['FREELANCE', 'CDI']);

        $phpSkill = new Skill('PHP', 'php', SkillCategory::BACKEND);
        new CandidateSkill($profile, $phpSkill, cvDocument: $document);

        $analysis = new CvAnalysisResult(
            suggestedTitle: 'Développeur PHP',
            location: 'Marseille',
            yearsOfExperience: 4,
            skills: [
                new AnalyzedSkill('PHP', SkillCategory::BACKEND, SkillLevel::ADVANCED, 4, true, 0.9),
                new AnalyzedSkill('Docker', SkillCategory::DEVOPS, SkillLevel::INTERMEDIATE, 2, false, 0.8),
            ],
            summary: 'Profil développeur',
            warnings: [],
        );

        $dto = CvReviewData::fromDocument($document, $analysis);

        self::assertSame('Architecte PHP', $dto->title);
        self::assertSame('Toulouse', $dto->location);
        self::assertSame(10, $dto->yearsOfExperience);
        self::assertSame(['PHP'], $dto->selectedSkills);
        self::assertSame(['FREELANCE', 'CDI'], $dto->contractTypes);
    }

    public function testCandidateProfileDetailsDataFromProfile(): void
    {
        $profile = new CandidateProfile();
        $profile->updateDetails('Lead Tech', 'Nantes', 8, ['CDI', 'APPRENTICESHIP']);

        $dto = CandidateProfileDetailsData::fromProfile($profile);

        self::assertSame('Lead Tech', $dto->title);
        self::assertSame('Nantes', $dto->location);
        self::assertSame(8, $dto->yearsOfExperience);
        self::assertSame(['CDI', 'APPRENTICESHIP'], $dto->preferredContractTypes);
        self::assertNull($dto->preferredRemotePolicy);
    }
}
