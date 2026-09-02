<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Job\Application\Service\SmartJobSearchQueryGenerator;
use PHPUnit\Framework\TestCase;

final class SmartJobSearchQueryGeneratorTest extends TestCase
{
    public function testItGeneratesOptimizedQueriesForGenericFullstackDeveloper(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur Full Stack', 'Lyon', 5, 'Texte CV');

        $phpSkill = new Skill('PHP 8', 'php-8', SkillCategory::BACKEND);
        $symfonySkill = new Skill('Symfony', 'symfony', SkillCategory::BACKEND);
        $reactSkill = new Skill('React', 'react', SkillCategory::FRONTEND);
        $gitSkill = new Skill('Git', 'git', SkillCategory::OTHER);
        $agileSkill = new Skill('Agile', 'agile', SkillCategory::METHODOLOGY);

        new CandidateSkill($profile, $phpSkill, SkillLevel::EXPERT, isCoreSkill: true);
        new CandidateSkill($profile, $symfonySkill, SkillLevel::ADVANCED, isCoreSkill: true);
        new CandidateSkill($profile, $reactSkill, SkillLevel::INTERMEDIATE, isCoreSkill: false);
        new CandidateSkill($profile, $gitSkill, SkillLevel::ADVANCED, isCoreSkill: false);
        new CandidateSkill($profile, $agileSkill, SkillLevel::ADVANCED, isCoreSkill: false);

        $generator = new SmartJobSearchQueryGenerator();
        $queries = $generator->generate($profile);

        self::assertContains('Développeur Full Stack', $queries);
        self::assertContains('Développeur Full Stack PHP', $queries);
        self::assertContains('Développeur PHP', $queries);
        self::assertContains('Développeur Symfony', $queries);
        self::assertContains('Développeur PHP Symfony', $queries);

        // Git and Agile should be excluded from search queries
        self::assertNotContains('Développeur Full Stack Git', $queries);
        self::assertNotContains('Développeur Agile', $queries);
    }

    public function testItGeneratesOptimizedQueriesForBackendDeveloper(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Dev Backend', 'Paris', 3, 'Texte CV');

        $pythonSkill = new Skill('Python', 'python', SkillCategory::BACKEND);
        $fastApiSkill = new Skill('FastAPI', 'fastapi', SkillCategory::BACKEND);

        new CandidateSkill($profile, $pythonSkill, SkillLevel::EXPERT, isCoreSkill: true);
        new CandidateSkill($profile, $fastApiSkill, SkillLevel::ADVANCED, isCoreSkill: true);

        $generator = new SmartJobSearchQueryGenerator();
        $queries = $generator->generate($profile);

        self::assertContains('Dev Backend', $queries);
        self::assertContains('Dev Backend Python', $queries);
        self::assertContains('Développeur Python', $queries);
        self::assertContains('Dev Backend FastAPI', $queries);
        self::assertContains('Développeur Python FastAPI', $queries);
    }

    public function testItGeneratesOptimizedQueriesForLeadDeveloper(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Lead Dev', 'Bordeaux', 8, 'Texte CV');

        $symfonySkill = new Skill('Symfony', 'symfony', SkillCategory::BACKEND);
        $phpSkill = new Skill('PHP', 'php', SkillCategory::BACKEND);

        new CandidateSkill($profile, $symfonySkill, SkillLevel::EXPERT, isCoreSkill: true);
        new CandidateSkill($profile, $phpSkill, SkillLevel::ADVANCED, isCoreSkill: true);

        $generator = new SmartJobSearchQueryGenerator();
        $queries = $generator->generate($profile);

        self::assertContains('Lead Dev', $queries);
        self::assertContains('Lead Dev Symfony', $queries);
        self::assertContains('Lead Developer Symfony', $queries);
        self::assertContains('Lead Developer Symfony PHP', $queries);
    }

    public function testItReturnsEmptyArrayWhenProfileHasNoTitle(): void
    {
        $profile = new CandidateProfile();
        $generator = new SmartJobSearchQueryGenerator();

        self::assertSame([], $generator->generate($profile));
    }

    public function testItReturnsOnlyBaseTitleWhenProfileHasNoSkills(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur Web', 'Nantes', 2, 'Texte CV');

        $generator = new SmartJobSearchQueryGenerator();
        $queries = $generator->generate($profile);

        self::assertSame(['Développeur Web'], $queries);
    }
}
