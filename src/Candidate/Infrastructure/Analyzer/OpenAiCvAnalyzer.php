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

final readonly class OpenAiCvAnalyzer implements CvAnalyzerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    public function analyze(string $cvText): CvAnalysisResult
    {
        if (trim($this->apiKey) === '') {
            throw new CvAnalysisException(CandidateMessage::MISSING_OPENAI_KEY, false);
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->model,
                    'store' => false,
                    'instructions' => 'Analyse ce CV sans inventer. Pour suggestedTitle, détermine l’intitulé professionnel principal ou le poste ciblé. Pour location, extrais la ville de résidence ou de recherche du candidat, et non la ville d’un ancien employeur. Extrais uniquement les informations explicitement présentes ou raisonnablement déductibles. Utilise null en cas de doute. Réponds en français et respecte strictement le schéma JSON.',
                    'input' => mb_substr($cvText, 0, 60000),
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'cv_analysis',
                            'strict' => true,
                            'schema' => self::schema(),
                        ],
                    ],
                    'max_output_tokens' => 4000,
                ],
                'timeout' => 45,
            ]);
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (TimeoutExceptionInterface $exception) {
            throw new CvAnalysisException(CandidateMessage::OPENAI_TIMEOUT, true, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw new CvAnalysisException(CandidateMessage::OPENAI_CONNECTION_FAILED, true, $exception);
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
            throw new CvAnalysisException(CandidateMessage::INVALID_OPENAI_JSON, true, $exception);
        }

        if (!is_array($decoded)) {
            throw new CvAnalysisException(CandidateMessage::EMPTY_OPENAI_ANALYSIS, true);
        }

        /* @var array<string, mixed> $decoded */
        return CvAnalysisResult::fromArray($decoded);
    }

    public function name(): string
    {
        return 'openai:'.$this->model;
    }

    /** @param array<string, mixed> $payload */
    private static function extractOutputText(array $payload): string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text']) && $payload['output_text'] !== '') {
            return $payload['output_text'];
        }

        $output = $payload['output'] ?? [];
        if (is_array($output)) {
            foreach ($output as $item) {
                if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                    continue;
                }
                foreach ($item['content'] as $content) {
                    if (is_array($content) && 'output_text' === ($content['type'] ?? null) && isset($content['text']) && is_string($content['text'])) {
                        return $content['text'];
                    }
                }
            }
        }

        throw new CvAnalysisException(CandidateMessage::MISSING_OPENAI_OUTPUT, true);
    }

    /** @param array<string, mixed> $payload */
    private static function createHttpException(int $statusCode, array $payload): CvAnalysisException
    {
        $error = $payload['error'] ?? null;
        $errorCode = is_array($error) && isset($error['code']) && is_string($error['code']) ? $error['code'] : null;

        if (in_array($statusCode, [401, 403], true)) {
            return new CvAnalysisException(CandidateMessage::OPENAI_AUTHENTICATION_FAILED, false);
        }

        if ($statusCode === 429 && $errorCode === 'insufficient_quota') {
            return new CvAnalysisException(CandidateMessage::OPENAI_QUOTA_EXCEEDED, false);
        }

        if ($statusCode === 429) {
            return new CvAnalysisException(CandidateMessage::OPENAI_RATE_LIMITED, true);
        }

        if ($statusCode >= 500) {
            return new CvAnalysisException(CandidateMessage::OPENAI_UNAVAILABLE, true);
        }

        return new CvAnalysisException(CandidateMessage::OPENAI_REQUEST_FAILED, false);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['suggestedTitle', 'location', 'yearsOfExperience', 'skills', 'summary', 'warnings'],
            'properties' => [
                'suggestedTitle' => ['type' => ['string', 'null']],
                'location' => ['type' => ['string', 'null']],
                'yearsOfExperience' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 80],
                'skills' => [
                    'type' => 'array',
                    'maxItems' => 100,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'category', 'level', 'yearsOfExperience', 'isCoreSkill', 'confidence'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'category' => ['type' => 'string', 'enum' => ['BACKEND', 'FRONTEND', 'DATABASE', 'DEVOPS', 'TESTING', 'METHODOLOGY', 'OTHER']],
                            'level' => ['type' => ['string', 'null'], 'enum' => ['BEGINNER', 'INTERMEDIATE', 'ADVANCED', 'EXPERT', null]],
                            'yearsOfExperience' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 80],
                            'isCoreSkill' => ['type' => 'boolean'],
                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                        ],
                    ],
                ],
                'summary' => ['type' => 'string'],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string'], 'maxItems' => 20],
            ],
        ];
    }
}
