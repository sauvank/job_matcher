<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\AnalyzedRequirement;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;
use App\Matching\Service\JobApplicationAssistantService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JobApplicationAssistantServiceTest extends TestCase
{
    public function testItGeneratesDeterministicApplicationAssistantResult(): void
    {
        $profile = new CandidateProfile();
        $profile->updateDetails('Développeur PHP Symfony', 'Lyon', 5, ['CDI'], 50000, 500);

        $skillPhp = new Skill('PHP', 'php', SkillCategory::BACKEND);
        $skillSymfony = new Skill('Symfony', 'symfony', SkillCategory::BACKEND);
        $profile->addCandidateSkill(new CandidateSkill($profile, $skillPhp, SkillLevel::EXPERT));
        $profile->addCandidateSkill(new CandidateSkill($profile, $skillSymfony, SkillLevel::ADVANCED));

        $source = new JobSource($profile, 'Apec — PHP', 'https://example.test', JobProviderType::APEC);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-1',
            url: 'https://example.test/1',
            title: 'Lead Developer Symfony',
            company: 'Acme Agency',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 55000,
            maximumSalary: 65000,
            remotePolicy: 'HYBRID',
            yearsOfExperience: 5,
            description: 'Conception d\'API et architecture logicielle.',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));

        $analysis = new SemanticJobAnalysis(
            compatibilityScore: 90,
            summary: 'Très bonne adéquation technique.',
            requirements: [
                new AnalyzedRequirement(
                    category: RequirementCategory::TECHNICAL,
                    importance: RequirementImportance::REQUIRED,
                    label: 'Symfony',
                    offerEvidence: 'Symfony 7',
                    assessment: RequirementAssessment::MATCH,
                    cvEvidence: '5 ans sur Symfony',
                    explanation: 'Excellente maîtrise.',
                ),
            ],
            strengths: ['Solide expertise Symfony', 'Expérience en architecture logicielle'],
            concerns: [],
            questions: ['Quelle est la méthodologie de test en place ?'],
        );

        $service = new JobApplicationAssistantService(new MockHttpClient());
        $result = $service->generate($profile, $offer, $analysis);

        self::assertStringContainsString('Lead Developer Symfony', $result->pitch);
        self::assertStringContainsString('Acme Agency', $result->pitch);
        self::assertStringContainsString('PHP, Symfony', $result->pitch);

        self::assertStringContainsString('Lead Developer Symfony', $result->coverLetter);
        self::assertStringContainsString('Solide expertise Symfony', $result->coverLetter);

        self::assertStringContainsString('Lead Developer Symfony', $result->followUpMessage);

        self::assertNotEmpty($result->interviewQuestions);
        self::assertSame('Quelle est la méthodologie de test en place ?', $result->interviewQuestions[0]['question']);
    }

    public function testItGeneratesWithOpenAiWhenConfigured(): void
    {
        $profile = new CandidateProfile();
        $profile->updateDetails('Dev PHP', 'Lyon', 4, ['CDI'], 45000, 450);

        $source = new JobSource($profile, 'Apec', 'https://example.test', JobProviderType::APEC);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-2',
            url: 'https://example.test/2',
            title: 'Développeur Backend',
            company: 'Tech SaaS',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 45000,
            maximumSalary: 50000,
            remotePolicy: 'REMOTE_AVAILABLE',
            yearsOfExperience: 3,
            description: 'API backend',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));

        $mockResponse = new MockResponse(json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'pitch' => 'Bonjour, votre offre de Développeur Backend chez Tech SaaS m\'intéresse grandement.',
                            'coverLetter' => 'Madame, Monsieur, je postule pour Développeur Backend chez Tech SaaS...',
                            'followUpMessage' => 'Bonjour, je me permets de relancer ma candidature...',
                            'interviewQuestions' => [
                                [
                                    'question' => 'Comment gérez-vous la dette technique ?',
                                    'context' => 'Validation de la rigueur.',
                                    'suggestedAnswer' => 'En appliquant du refactoring continu.',
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $client = new MockHttpClient([$mockResponse]);
        $service = new JobApplicationAssistantService($client, 'sk-test', 'gpt-5.6-luna', 'openai');
        $result = $service->generate($profile, $offer, null);

        self::assertSame('Bonjour, votre offre de Développeur Backend chez Tech SaaS m\'intéresse grandement.', $result->pitch);
        self::assertSame('Madame, Monsieur, je postule pour Développeur Backend chez Tech SaaS...', $result->coverLetter);
        self::assertCount(1, $result->interviewQuestions);
        self::assertSame('Comment gérez-vous la dette technique ?', $result->interviewQuestions[0]['question']);
    }
}
