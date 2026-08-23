<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Service\TechnicalRequirementExtractor;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\Infrastructure\Analyzer\ConfiguredJobSemanticAnalyzer;
use App\Matching\Infrastructure\Analyzer\FakeJobSemanticAnalyzer;
use App\Matching\Infrastructure\Analyzer\GeminiJobSemanticAnalyzer;
use App\Matching\Infrastructure\Analyzer\OpenAiJobSemanticAnalyzer;
use App\Matching\Translation\MatchingMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredJobSemanticAnalyzerTest extends TestCase
{
    public function testItSelectsFakeAnalyzer(): void
    {
        $configured = new ConfiguredJobSemanticAnalyzer(
            new FakeJobSemanticAnalyzer(new TechnicalRequirementExtractor()),
            new OpenAiJobSemanticAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiJobSemanticAnalyzer(new MockHttpClient(), 'key', 'model'),
            'fake',
        );

        self::assertSame('fake', $configured->name());
        $analysis = $configured->analyze(new CandidateProfile(), $this->offer());
        self::assertGreaterThanOrEqual(0, $analysis->compatibilityScore);
    }

    public function testItSelectsGeminiAnalyzer(): void
    {
        $output = json_encode([
            'compatibilityScore' => 88,
            'summary' => 'Très bonne adéquation.',
            'requirements' => [
                [
                    'category' => 'TECHNICAL',
                    'importance' => 'REQUIRED',
                    'label' => 'PHP',
                    'offerEvidence' => 'PHP 8 requis',
                    'assessment' => 'MATCH',
                    'cvEvidence' => 'PHP 8 depuis 3 ans',
                    'explanation' => 'Conforme.',
                ],
            ],
            'strengths' => ['PHP 8'],
            'concerns' => [],
            'questions' => [],
        ], JSON_THROW_ON_ERROR);

        $responsePayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $output],
                        ],
                    ],
                ],
            ],
        ];

        $client = new MockHttpClient(new MockResponse(json_encode($responsePayload, JSON_THROW_ON_ERROR)));

        $configured = new ConfiguredJobSemanticAnalyzer(
            new FakeJobSemanticAnalyzer(new TechnicalRequirementExtractor()),
            new OpenAiJobSemanticAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiJobSemanticAnalyzer($client, 'gemini-key', 'gemini-2.0-flash'),
            'gemini',
        );

        self::assertSame('gemini:gemini-2.0-flash', $configured->name());
        $analysis = $configured->analyze(new CandidateProfile(), $this->offer());
        self::assertSame(88, $analysis->compatibilityScore);
    }

    public function testItThrowsOnUnknownMode(): void
    {
        $configured = new ConfiguredJobSemanticAnalyzer(
            new FakeJobSemanticAnalyzer(new TechnicalRequirementExtractor()),
            new OpenAiJobSemanticAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiJobSemanticAnalyzer(new MockHttpClient(), 'key', 'model'),
            'unknown',
        );

        $this->expectException(SemanticAnalysisException::class);
        $this->expectExceptionMessage(MatchingMessage::UNKNOWN_SEMANTIC_ANALYZER);

        $configured->analyze(new CandidateProfile(), $this->offer());
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'HelloWork', 'https://example.test', JobProviderType::HELLOWORK),
            new NormalizedJobOffer(
                externalId: '123',
                url: 'https://example.test/123',
                title: 'Poste Test',
                company: null,
                location: null,
                contractType: null,
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: null,
                yearsOfExperience: null,
                description: 'Description',
                publishedAt: null,
                validThrough: null,
                rawPayload: [],
            ),
        );
    }
}
