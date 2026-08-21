<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\RemotePolicy;
use App\Candidate\Enum\SkillCategory;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Service\DeterministicJobScorer;
use PHPUnit\Framework\TestCase;

final class DeterministicJobScorerTest extends TestCase
{
    public function testItScoresACompatibleOfferAtOneHundred(): void
    {
        $profile = $this->profile();
        $offer = $this->offer(
            title: 'Développeur backend PHP Symfony',
            location: 'Lyon 69000',
            contract: 'CDI',
            minimumSalary: 40000,
            maximumSalary: 50000,
            remotePolicy: 'REMOTE_AVAILABLE',
            requiredExperience: 4,
            description: 'API Symfony avec PHP et Docker.',
        );

        $score = $this->scorer()->score($profile, $offer);

        self::assertSame(100, $score->globalScore);
        self::assertSame(100, $score->stackScore);
        self::assertSame(100, $score->experienceScore);
        self::assertSame(100, $score->salaryScore);
        self::assertSame(100, $score->locationScore);
        self::assertSame(100, $score->contractScore);
        self::assertSame(100, $score->remoteScore);
        self::assertSame(100, $score->backendScore);
        self::assertNotEmpty($score->strengths);
        self::assertSame([], $score->blockers);
    }

    public function testItExplainsAnIncompatibleOffer(): void
    {
        $offer = $this->offer(
            title: 'Développeur Java',
            location: 'Paris 75000',
            contract: 'CDD',
            minimumSalary: 25000,
            maximumSalary: 30000,
            remotePolicy: null,
            requiredExperience: 10,
            description: 'Application Java historique.',
        );

        $score = $this->scorer()->score($this->profile(), $offer);

        self::assertLessThan(40, $score->globalScore);
        self::assertSame(0, $score->stackScore);
        self::assertSame(0, $score->contractScore);
        self::assertSame(0, $score->remoteScore);
        self::assertNotEmpty($score->gaps);
        self::assertNotEmpty($score->unknowns);
    }

    private function profile(): CandidateProfile
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur backend PHP/Symfony', 'Lyon 69000', 6, 'CV PHP Symfony Docker');
        $profile->updatePreferences(45000, ['CDI'], RemotePolicy::HYBRID);
        new CandidateSkill($profile, new Skill('PHP', 'php', SkillCategory::BACKEND), isCoreSkill: true);
        new CandidateSkill($profile, new Skill('Symfony', 'symfony', SkillCategory::BACKEND), isCoreSkill: true);
        new CandidateSkill($profile, new Skill('Docker', 'docker', SkillCategory::DEVOPS));

        return $profile;
    }

    private function offer(
        string $title,
        string $location,
        string $contract,
        int $minimumSalary,
        int $maximumSalary,
        ?string $remotePolicy,
        int $requiredExperience,
        string $description,
    ): JobOffer {
        $source = new JobSource('Test', 'https://example.test', JobProviderType::FAKE);
        $normalized = new NormalizedJobOffer(
            externalId: 'offer-1',
            url: 'https://example.test/offer-1',
            title: $title,
            company: 'Test',
            location: $location,
            contractType: $contract,
            minimumSalary: $minimumSalary,
            maximumSalary: $maximumSalary,
            remotePolicy: $remotePolicy,
            yearsOfExperience: $requiredExperience,
            description: $description,
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        );

        return new JobOffer($source, $normalized);
    }

    private function scorer(): DeterministicJobScorer
    {
        return new DeterministicJobScorer([
            'stack' => 35,
            'experience' => 15,
            'salary' => 15,
            'location' => 10,
            'contract' => 10,
            'backend' => 10,
            'remote' => 5,
        ]);
    }
}
