<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Infrastructure\Analyzer\GeminiCvAnalyzer;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeminiCvAnalyzerTest extends TestCase
{
    public function testItParsesAStructuredResponse(): void
    {
        $output = json_encode([
            'suggestedTitle' => 'Backend Engineer',
            'location' => 'Paris',
            'yearsOfExperience' => 4,
            'skills' => [
                [
                    'name' => 'PHP',
                    'category' => 'BACKEND',
                    'level' => 'ADVANCED',
                    'yearsOfExperience' => 4,
                    'isCoreSkill' => true,
                    'confidence' => 0.95,
                ],
            ],
            'summary' => 'Profil backend confirmé.',
            'warnings' => [],
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

        $analyzer = new GeminiCvAnalyzer($client, 'gemini-test-key', 'gemini-2.0-flash');
        $analysis = $analyzer->analyze('CV suffisamment long pour le test.');

        self::assertSame('gemini:gemini-2.0-flash', $analyzer->name());
        self::assertSame('Backend Engineer', $analysis->suggestedTitle);
        self::assertSame('Paris', $analysis->location);
        self::assertSame(4, $analysis->yearsOfExperience);
        self::assertCount(1, $analysis->skills);
        self::assertSame('PHP', $analysis->skills[0]->name);

        $requestOptions = $mockResponse->getRequestOptions();
        self::assertSame('POST', $mockResponse->getRequestMethod());
        self::assertStringContainsString('gemini-2.0-flash:generateContent', $mockResponse->getRequestUrl());
        self::assertContains('x-goog-api-key: gemini-test-key', $requestOptions['headers'] ?? []);

        $requestBody = json_decode((string) $requestOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('application/json', $requestBody['generationConfig']['responseMimeType']);
        self::assertSame(8192, $requestBody['generationConfig']['maxOutputTokens']);
        self::assertArrayHasKey('responseSchema', $requestBody['generationConfig']);
    }

    public function testItThrowsExceptionWhenApiKeyIsMissing(): void
    {
        $this->expectException(CvAnalysisException::class);
        $this->expectExceptionMessage(CandidateMessage::MISSING_GEMINI_KEY);

        (new GeminiCvAnalyzer(new MockHttpClient(), '', 'gemini-2.0-flash'))->analyze('CV test');
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
            (new GeminiCvAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::GEMINI_QUOTA_EXCEEDED, $exception->getMessage());
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
            (new GeminiCvAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::GEMINI_RATE_LIMITED, $exception->getMessage());
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
            (new GeminiCvAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::GEMINI_AUTHENTICATION_FAILED, $exception->getMessage());
            self::assertFalse($exception->retryable);
        }
    }

    public function testItReportsServerErrorAndAllowsRetry(): void
    {
        $response = new MockResponse(
            json_encode(['error' => ['code' => 503, 'message' => 'Service Unavailable']], JSON_THROW_ON_ERROR),
            ['http_code' => 503],
        );

        try {
            (new GeminiCvAnalyzer(new MockHttpClient($response), 'test-key', 'gemini-2.0-flash'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::GEMINI_UNAVAILABLE, $exception->getMessage());
            self::assertTrue($exception->retryable);
        }
    }
}
