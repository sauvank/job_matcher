<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\Infrastructure\Analyzer\OpenAiJobSemanticAnalyzer;
use App\Matching\Translation\MatchingMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OpenAiJobSemanticAnalyzerTest extends TestCase
{
    /** @param array<string, string> $error */
    #[DataProvider('rateLimitErrors')]
    public function testItDistinguishesExhaustedCreditFromRateLimiting(array $error, string $expectedMessage, bool $retryable): void
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['error' => $error], JSON_THROW_ON_ERROR), ['http_code' => 429]));

        try {
            (new OpenAiJobSemanticAnalyzer($client, 'test-key', 'test-model'))->analyze(new CandidateProfile(), $this->offer());
            self::fail('Une exception était attendue.');
        } catch (SemanticAnalysisException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame($retryable, $exception->retryable);
        }
    }

    /** @return iterable<string, array{array<string, string>, string, bool}> */
    public static function rateLimitErrors(): iterable
    {
        yield 'credit balance exhausted' => [['type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted'], MatchingMessage::OPENAI_QUOTA_EXCEEDED, false];
        yield 'legacy insufficient quota' => [['code' => 'insufficient_quota'], MatchingMessage::OPENAI_QUOTA_EXCEEDED, false];
        yield 'rate limited' => [['type' => 'requests', 'code' => 'rate_limit_exceeded'], MatchingMessage::OPENAI_RATE_LIMITED, true];
    }

    public function testItSendsTheFullContextAndParsesStructuredEvidence(): void
    {
        $output = json_encode([
            'compatibilityScore' => 62,
            'summary' => 'PHP correspond, Angular reste à confirmer.',
            'jobSummary' => 'Développement d’applications web Symfony et Angular.',
            'keyExpectations' => ['Développer des fonctionnalités Symfony', 'Intégrer les interfaces Angular'],
            'requiredCapacities' => ['PHP', 'Symfony', 'Angular'],
            'requirements' => [[
                'category' => 'TECHNICAL',
                'importance' => 'REQUIRED',
                'label' => 'Angular',
                'offerEvidence' => 'développement front avec Angular',
                'assessment' => 'UNKNOWN',
                'cvEvidence' => null,
                'explanation' => 'Angular n’apparaît pas dans le CV.',
            ]],
            'strengths' => ['PHP confirmé.'],
            'concerns' => ['Angular non confirmé.'],
            'questions' => ['Angular est-il obligatoire ?'],
        ], JSON_THROW_ON_ERROR);
        $response = new MockResponse(json_encode(['output_text' => $output], JSON_THROW_ON_ERROR));
        $client = new MockHttpClient($response);
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur PHP', 'Lyon', 10, 'CV complet avec PHP et Symfony.');

        $analysis = (new OpenAiJobSemanticAnalyzer($client, 'test-key', 'test-model'))->analyze($profile, $this->offer());

        self::assertSame('Angular', $analysis->requirements[0]->label);
        self::assertSame(62, $analysis->compatibilityScore);
        self::assertSame('Développement d’applications web Symfony et Angular.', $analysis->jobSummary);
        self::assertCount(2, $analysis->keyExpectations);
        self::assertCount(3, $analysis->requiredCapacities);
        $requestBody = json_decode((string) $response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($requestBody['store'] === false);
        self::assertTrue($requestBody['text']['format']['strict']);
        self::assertStringContainsString('Angular', $requestBody['input']);
        self::assertStringContainsString('CV complet', $requestBody['input']);
        self::assertStringContainsString('certification REQUIRED sans preuve explicite', $requestBody['instructions']);
        self::assertStringContainsString('plafonne le score global à 69', $requestBody['instructions']);
        self::assertStringContainsString('Ne compte jamais séparément un diplôme et son titre RNCP', $requestBody['instructions']);
        self::assertContains('jobSummary', $requestBody['text']['format']['schema']['required']);
        self::assertContains('keyExpectations', $requestBody['text']['format']['schema']['required']);
        self::assertContains('requiredCapacities', $requestBody['text']['format']['schema']['required']);
        self::assertContains('CERTIFICATION', $requestBody['text']['format']['schema']['properties']['requirements']['items']['properties']['category']['enum']);
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
