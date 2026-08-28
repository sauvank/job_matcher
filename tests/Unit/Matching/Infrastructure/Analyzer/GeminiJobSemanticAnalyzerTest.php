<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\Infrastructure\Analyzer\GeminiJobSemanticAnalyzer;
use App\Matching\Translation\MatchingMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiJobSemanticAnalyzerTest extends TestCase
{
    public function testItSendsTheFullContextAndParsesStructuredEvidence(): void
    {
        $output = json_encode([
            'compatibilityScore' => 75,
            'summary' => 'PHP et Symfony correspondent, Angular à clarifier.',
            'jobSummary' => 'Développement d’applications web Symfony et Angular.',
            'keyExpectations' => ['Développer des fonctionnalités Symfony', 'Intégrer les interfaces Angular'],
            'requiredCapacities' => ['Symfony', 'Angular', 'PHP'],
            'requirements' => [
                [
                    'category' => 'TECHNICAL',
                    'importance' => 'REQUIRED',
                    'label' => 'Symfony',
                    'offerEvidence' => 'Maîtrise de Symfony exigée',
                    'assessment' => 'MATCH',
                    'cvEvidence' => 'Développeur Symfony depuis 5 ans',
                    'explanation' => 'Symfony est bien maîtrisé.',
                ],
                [
                    'category' => 'TECHNICAL',
                    'importance' => 'PREFERRED',
                    'label' => 'Angular',
                    'offerEvidence' => 'Connaissances en Angular appréciées',
                    'assessment' => 'UNKNOWN',
                    'cvEvidence' => null,
                    'explanation' => 'Angular n’apparaît pas dans le CV.',
                ],
            ],
            'strengths' => ['Excellente maîtrise de Symfony.'],
            'concerns' => ['Angular non mentionné.'],
            'questions' => ['Avez-vous une expérience avec Angular ?'],
        ], JSON_THROW_ON_ERROR);

        $responsePayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $output],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
        ];

        $mockResponse = new MockResponse(json_encode($responsePayload, JSON_THROW_ON_ERROR));
        $client = new MockHttpClient($mockResponse);
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur PHP', 'Lyon', 10, 'CV complet avec PHP et Symfony.');

        $analyzer = new GeminiJobSemanticAnalyzer($client, 'gemini-key', 'gemini-2.0-flash');
        $analysis = $analyzer->analyze($profile, $this->offer());

        self::assertSame('gemini:gemini-2.0-flash', $analyzer->name());
        self::assertSame(75, $analysis->compatibilityScore);
        self::assertSame('Développement d’applications web Symfony et Angular.', $analysis->jobSummary);
        self::assertCount(2, $analysis->keyExpectations);
        self::assertCount(3, $analysis->requiredCapacities);
        self::assertCount(2, $analysis->requirements);
        self::assertSame('Symfony', $analysis->requirements[0]->label);
        self::assertSame('MATCH', $analysis->requirements[0]->assessment->value);
        self::assertSame('Angular', $analysis->requirements[1]->label);
        self::assertSame('UNKNOWN', $analysis->requirements[1]->assessment->value);

        $requestOptions = $mockResponse->getRequestOptions();
        self::assertSame('POST', $mockResponse->getRequestMethod());
        self::assertStringContainsString('gemini-2.0-flash:generateContent', $mockResponse->getRequestUrl());
        self::assertContains('x-goog-api-key: gemini-key', $requestOptions['headers'] ?? []);

        $requestBody = json_decode((string) $requestOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('application/json', $requestBody['generationConfig']['responseMimeType']);
        self::assertStringContainsString('Angular', $requestBody['contents'][0]['parts'][0]['text']);
        self::assertStringContainsString('CV complet', $requestBody['contents'][0]['parts'][0]['text']);
        self::assertStringContainsString('certification REQUIRED sans preuve explicite', $requestBody['system_instruction']['parts'][0]['text']);
    }

    public function testItThrowsExceptionWhenApiKeyIsMissing(): void
    {
        $this->expectException(SemanticAnalysisException::class);
        $this->expectExceptionMessage(MatchingMessage::MISSING_GEMINI_KEY);

        $profile = new CandidateProfile();
        (new GeminiJobSemanticAnalyzer(new MockHttpClient(), '', 'gemini-2.0-flash'))->analyze($profile, $this->offer());
    }

    public function testItReportsAnInsufficientQuotaWithoutRetrying(): void
    {
        $response = new MockResponse(
            json_encode([
                'error' => [
                    'code' => 429,
                    'status' => 'RESOURCE_EXHAUSTED',
                    'message' => 'Quota exceeded for quota metric GenerateContent',
                ],
            ], JSON_THROW_ON_ERROR),
            ['http_code' => 429],
        );

        try {
            $profile = new CandidateProfile();
            (new GeminiJobSemanticAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze($profile, $this->offer());
            self::fail('Une exception était attendue.');
        } catch (SemanticAnalysisException $exception) {
            self::assertSame(MatchingMessage::GEMINI_QUOTA_EXCEEDED, $exception->getMessage());
            self::assertFalse($exception->retryable);
        }
    }

    public function testItReportsARateLimitAndAllowsARetry(): void
    {
        $response = new MockResponse(
            json_encode([
                'error' => [
                    'code' => 429,
                    'status' => 'RESOURCE_EXHAUSTED',
                    'message' => 'Rate limit exceeded: please slow down requests',
                ],
            ], JSON_THROW_ON_ERROR),
            ['http_code' => 429],
        );

        try {
            $profile = new CandidateProfile();
            (new GeminiJobSemanticAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze($profile, $this->offer());
            self::fail('Une exception était attendue.');
        } catch (SemanticAnalysisException $exception) {
            self::assertSame(MatchingMessage::GEMINI_RATE_LIMITED, $exception->getMessage());
            self::assertTrue($exception->retryable);
        }
    }

    public function testItReportsAuthenticationError(): void
    {
        $response = new MockResponse(
            json_encode([
                'error' => [
                    'code' => 400,
                    'status' => 'INVALID_ARGUMENT',
                    'message' => 'API key not valid. Please pass a valid API key.',
                ],
            ], JSON_THROW_ON_ERROR),
            ['http_code' => 400],
        );

        try {
            $profile = new CandidateProfile();
            (new GeminiJobSemanticAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze($profile, $this->offer());
            self::fail('Une exception était attendue.');
        } catch (SemanticAnalysisException $exception) {
            self::assertSame(MatchingMessage::GEMINI_AUTHENTICATION_FAILED, $exception->getMessage());
            self::assertFalse($exception->retryable);
        }
    }

    private function offer(): JobOffer
    {
        return new JobOffer(
            new JobSource(new CandidateProfile(), 'HelloWork', 'https://example.test', JobProviderType::HELLOWORK),
            new NormalizedJobOffer(
                externalId: '79162910',
                url: 'https://www.hellowork.com/fr-fr/emplois/79162910.html',
                title: 'Développeur Symfony Angular',
                company: 'Test',
                location: 'Lyon',
                contractType: 'CDI',
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: null,
                yearsOfExperience: 5,
                description: 'Stack PHP, Symfony et Angular.',
                publishedAt: null,
                validThrough: null,
                rawPayload: ['skills' => ['PHP'], 'qualifications' => 'Angular demandé'],
            ),
        );
    }
}
