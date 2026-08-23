<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Translation\CandidateMessage;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeminiCvAnalyzer implements CvAnalyzerInterface
{
    private const INSTRUCTIONS = 'Analyse ce CV sans inventer. Pour suggestedTitle, détermine l’intitulé professionnel principal ou le poste ciblé. Pour location, extrais la ville de résidence ou de recherche du candidat, et non la ville d’un ancien employeur. Extrais uniquement les informations explicitement présentes ou raisonnablement déductibles. Utilise null en cas de doute. Réponds en français et respecte strictement le schéma JSON.';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    public function analyze(string $cvText): CvAnalysisResult
    {
        if (trim($this->apiKey) === '') {
            throw new CvAnalysisException(CandidateMessage::MISSING_GEMINI_KEY, false);
        }

        try {
            $endpoint = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', urlencode($this->model));
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'json' => [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => self::INSTRUCTIONS],
                        ],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => mb_substr($cvText, 0, 60000)],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                        'responseSchema' => self::schema(),
                        'maxOutputTokens' => 8192,
                        'temperature' => 0.1,
                    ],
                ],
                'timeout' => 45,
            ]);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (TimeoutExceptionInterface $exception) {
            throw new CvAnalysisException(CandidateMessage::GEMINI_TIMEOUT, true, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw new CvAnalysisException(CandidateMessage::GEMINI_CONNECTION_FAILED, true, $exception);
        }

        $decodedResponse = json_decode($responseBody, true);
        $payload = is_array($decodedResponse) ? $decodedResponse : [];

        if ($statusCode >= 400) {
            throw self::createHttpException($statusCode, $payload);
        }

        $outputText = self::extractOutputText($payload);

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CvAnalysisException(CandidateMessage::INVALID_GEMINI_JSON, true, $exception);
        }

        if (!is_array($decoded)) {
            throw new CvAnalysisException(CandidateMessage::EMPTY_GEMINI_ANALYSIS, true);
        }

        /* @var array<string, mixed> $decoded */
        return CvAnalysisResult::fromArray($decoded);
    }

    public function name(): string
    {
        return 'gemini:'.$this->model;
    }

    /** @param array<string, mixed> $payload */
    private static function extractOutputText(array $payload): string
    {
        $candidates = $payload['candidates'] ?? null;
        if (!is_array($candidates) || !isset($candidates[0]) || !is_array($candidates[0])) {
            throw new CvAnalysisException(CandidateMessage::MISSING_GEMINI_OUTPUT, true);
        }

        $parts = $candidates[0]['content']['parts'] ?? null;
        if (!is_array($parts) || !isset($parts[0]['text']) || !is_string($parts[0]['text']) || trim($parts[0]['text']) === '') {
            throw new CvAnalysisException(CandidateMessage::MISSING_GEMINI_OUTPUT, true);
        }

        return $parts[0]['text'];
    }

    /** @param array<string, mixed> $payload */
    private static function createHttpException(int $statusCode, array $payload): CvAnalysisException
    {
        $error = $payload['error'] ?? null;
        $status = is_array($error) && isset($error['status']) && is_string($error['status']) ? $error['status'] : null;
        $message = is_array($error) && isset($error['message']) && is_string($error['message']) ? $error['message'] : '';

        if (in_array($statusCode, [401, 403], true) || ($statusCode === 400 && str_contains(strtolower($message), 'api key'))) {
            return new CvAnalysisException(CandidateMessage::GEMINI_AUTHENTICATION_FAILED, false);
        }

        if ($statusCode === 429) {
            if ($status === 'RESOURCE_EXHAUSTED' && str_contains(strtolower($message), 'quota')) {
                return new CvAnalysisException(CandidateMessage::GEMINI_QUOTA_EXCEEDED, false);
            }

            return new CvAnalysisException(CandidateMessage::GEMINI_RATE_LIMITED, true);
        }

        if ($statusCode >= 500) {
            return new CvAnalysisException(CandidateMessage::GEMINI_UNAVAILABLE, true);
        }

        return new CvAnalysisException(CandidateMessage::GEMINI_REQUEST_FAILED, false);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'suggestedTitle' => ['type' => 'string', 'nullable' => true],
                'location' => ['type' => 'string', 'nullable' => true],
                'yearsOfExperience' => ['type' => 'integer', 'nullable' => true],
                'skills' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => ['BACKEND', 'FRONTEND', 'DATABASE', 'DEVOPS', 'TESTING', 'METHODOLOGY', 'OTHER']],
                            'level' => ['type' => 'string', 'enum' => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED', 'EXPERT'], 'nullable' => true],
                            'yearsOfExperience' => ['type' => 'integer', 'nullable' => true],
                            'isCoreSkill' => ['type' => 'boolean'],
                            'confidence' => ['type' => 'number'],
                        ],
                        'required' => ['name', 'category', 'isCoreSkill', 'confidence'],
                    ],
                ],
                'summary' => ['type' => 'string'],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['suggestedTitle', 'location', 'yearsOfExperience', 'skills', 'summary', 'warnings'],
        ];
    }
}
