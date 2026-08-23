<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Infrastructure\Analyzer\ConfiguredCvAnalyzer;
use App\Candidate\Infrastructure\Analyzer\FakeCvAnalyzer;
use App\Candidate\Infrastructure\Analyzer\GeminiCvAnalyzer;
use App\Candidate\Infrastructure\Analyzer\OpenAiCvAnalyzer;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ConfiguredCvAnalyzerTest extends TestCase
{
    public function testItSelectsFakeAnalyzer(): void
    {
        $configured = new ConfiguredCvAnalyzer(
            'fake',
            new FakeCvAnalyzer(),
            new OpenAiCvAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiCvAnalyzer(new MockHttpClient(), 'key', 'model'),
        );

        self::assertSame('fake', $configured->name());
        $result = $configured->analyze('CV avec expérience Symfony');
        self::assertSame('Développeur backend PHP/Symfony', $result->suggestedTitle);
    }

    public function testItSelectsGeminiAnalyzer(): void
    {
        $responsePayload = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode([
                                    'suggestedTitle' => 'Lead Dev PHP',
                                    'location' => 'Nantes',
                                    'yearsOfExperience' => 7,
                                    'skills' => [],
                                    'summary' => 'Profil lead.',
                                    'warnings' => [],
                                ], JSON_THROW_ON_ERROR),
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $client = new MockHttpClient(new MockResponse(json_encode($responsePayload, JSON_THROW_ON_ERROR)));

        $configured = new ConfiguredCvAnalyzer(
            'gemini',
            new FakeCvAnalyzer(),
            new OpenAiCvAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiCvAnalyzer($client, 'gemini-key', 'gemini-2.0-flash'),
        );

        self::assertSame('gemini:gemini-2.0-flash', $configured->name());
        $result = $configured->analyze('CV contenu');
        self::assertSame('Lead Dev PHP', $result->suggestedTitle);
    }

    public function testItThrowsOnUnknownMode(): void
    {
        $configured = new ConfiguredCvAnalyzer(
            'unknown',
            new FakeCvAnalyzer(),
            new OpenAiCvAnalyzer(new MockHttpClient(), 'key', 'model'),
            new GeminiCvAnalyzer(new MockHttpClient(), 'key', 'model'),
        );

        $this->expectException(CvAnalysisException::class);
        $this->expectExceptionMessage(CandidateMessage::UNKNOWN_ANALYZER);

        $configured->analyze('CV');
    }
}
