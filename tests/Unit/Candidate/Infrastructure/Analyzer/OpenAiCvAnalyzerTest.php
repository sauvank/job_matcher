<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Infrastructure\Analyzer\OpenAiCvAnalyzer;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiCvAnalyzerTest extends TestCase
{
    public function testItParsesAStructuredResponse(): void
    {
        $output = json_encode([
            'suggestedTitle' => 'Backend Engineer',
            'location' => null,
            'yearsOfExperience' => 4,
            'skills' => [],
            'summary' => 'Profil backend.',
            'warnings' => [],
        ], JSON_THROW_ON_ERROR);
        $client = new MockHttpClient(new MockResponse(json_encode(['output_text' => $output], JSON_THROW_ON_ERROR)));

        $analysis = (new OpenAiCvAnalyzer($client, 'test-key', 'test-model'))->analyze('CV suffisamment long pour le test.');

        self::assertSame('Backend Engineer', $analysis->suggestedTitle);
        self::assertSame(4, $analysis->yearsOfExperience);
    }

    public function testItReportsAnInsufficientQuotaWithoutRetrying(): void
    {
        $response = new MockResponse(
            json_encode(['error' => ['code' => 'insufficient_quota']], JSON_THROW_ON_ERROR),
            ['http_code' => 429],
        );

        try {
            (new OpenAiCvAnalyzer(new MockHttpClient($response), 'test-key', 'test-model'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::OPENAI_QUOTA_EXCEEDED, $exception->getMessage());
            self::assertFalse($exception->retryable);
        }
    }

    public function testItReportsARateLimitAndAllowsARetry(): void
    {
        $response = new MockResponse(
            json_encode(['error' => ['code' => 'rate_limit_exceeded']], JSON_THROW_ON_ERROR),
            ['http_code' => 429],
        );

        try {
            (new OpenAiCvAnalyzer(new MockHttpClient($response), 'test-key', 'test-model'))->analyze('CV de test');
            self::fail('Une exception était attendue.');
        } catch (CvAnalysisException $exception) {
            self::assertSame(CandidateMessage::OPENAI_RATE_LIMITED, $exception->getMessage());
            self::assertTrue($exception->retryable);
        }
    }
}
