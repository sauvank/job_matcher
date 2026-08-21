<?php

declare(strict_types=1);

namespace App\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Application\Analyzer\JobSemanticAnalyzerInterface;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Translation\MatchingMessage;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenAiJobSemanticAnalyzer implements JobSemanticAnalyzerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    public function analyze(CandidateProfile $profile, JobOffer $offer): SemanticJobAnalysis
    {
        if (trim($this->apiKey) === '') {
            throw new SemanticAnalysisException(MatchingMessage::MISSING_OPENAI_KEY, false);
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/responses', [
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Content-Type' => 'application/json'],
                'json' => [
                    'model' => $this->model,
                    'store' => false,
                    'instructions' => <<<'PROMPT'
                        Tu es un analyste de recrutement rigoureux. Extrais TOUTES les exigences et caractéristiques significatives de l’annonce : technologies, versions, méthodes, expérience, formation, responsabilités, séniorité, domaine, qualités humaines et conditions de travail. Ne te limite jamais au champ skills du fournisseur.

                        Pour chaque exigence, cite un court extrait exact de l’annonce dans offerEvidence. Classe REQUIRED seulement si le texte l’impose clairement, PREFERRED si le texte dit idéalement/souhaité/serait un plus, CONTEXT si ce n’est pas une exigence candidat. Compare ensuite au CV : MATCH exige une preuve explicite dans le CV, PARTIAL une preuve proche mais incomplète, GAP une contradiction ou une insuffisance explicite, UNKNOWN si le CV ne permet pas de conclure. N’invente jamais une compétence à partir d’une compétence voisine. Pour UNKNOWN ou GAP sans preuve CV, cvEvidence doit être null.

                        Retourne compatibilityScore, un entier de 0 à 100 représentant la compatibilité globale. Pondère fortement les exigences REQUIRED, plus faiblement les PREFERRED, et ne pénalise pas automatiquement une information UNKNOWN comme une incompatibilité certaine. Le score doit être cohérent avec les verdicts et expliqué par summary, strengths et concerns. Réponds en français, déduplique les exigences, sépare les technologies distinctes et respecte strictement le schéma JSON.
                        PROMPT,
                    'input' => $this->input($profile, $offer),
                    'text' => ['format' => ['type' => 'json_schema', 'name' => 'job_compatibility_analysis', 'strict' => true, 'schema' => self::schema()]],
                    'max_output_tokens' => 8000,
                ],
                'timeout' => 90,
            ]);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TimeoutExceptionInterface $exception) {
            throw new SemanticAnalysisException(MatchingMessage::OPENAI_TIMEOUT, true, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw new SemanticAnalysisException(MatchingMessage::OPENAI_CONNECTION_FAILED, true, $exception);
        }

        $decodedResponse = json_decode($body, true);
        $payload = is_array($decodedResponse) ? $decodedResponse : [];
        if ($statusCode >= 400) {
            throw self::httpException($statusCode, $payload);
        }

        try {
            $data = json_decode(self::outputText($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SemanticAnalysisException(MatchingMessage::INVALID_OPENAI_JSON, true, $exception);
        }
        if (!is_array($data)) {
            throw new SemanticAnalysisException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS, true);
        }

        /* @var array<string, mixed> $data */
        return SemanticJobAnalysis::fromArray($data);
    }

    public function name(): string
    {
        return 'openai:'.$this->model;
    }

    private function input(CandidateProfile $profile, JobOffer $offer): string
    {
        $skills = [];
        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            $skills[] = [
                'name' => $candidateSkill->getSkill()->getName(),
                'level' => $candidateSkill->getLevel()?->value,
                'yearsOfExperience' => $candidateSkill->getYearsOfExperience(),
                'core' => $candidateSkill->isCoreSkill(),
            ];
        }

        return json_encode([
            'candidate' => [
                'title' => $profile->getTitle(),
                'yearsOfExperience' => $profile->getYearsOfExperience(),
                'skillsValidated' => $skills,
                'cvText' => mb_substr((string) $profile->getRawCvText(), 0, 50000),
            ],
            'offer' => [
                'title' => $offer->getTitle(),
                'company' => $offer->getCompany(),
                'location' => $offer->getLocation(),
                'contract' => $offer->getContractType(),
                'description' => mb_substr((string) $offer->getDescription(), 0, 50000),
                'structuredData' => $offer->getRawPayload(),
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string, mixed> $payload */
    private static function outputText(array $payload): string
    {
        if (isset($payload['output_text']) && is_string($payload['output_text']) && $payload['output_text'] !== '') {
            return $payload['output_text'];
        }
        $output = $payload['output'] ?? [];
        if (is_array($output)) {
            foreach ($output as $item) {
                if (!is_array($item) || !is_array($item['content'] ?? null)) {
                    continue;
                }
                foreach ($item['content'] as $content) {
                    if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                        return $content['text'];
                    }
                }
            }
        }
        throw new SemanticAnalysisException(MatchingMessage::MISSING_OPENAI_OUTPUT, true);
    }

    /** @param array<string, mixed> $payload */
    private static function httpException(int $statusCode, array $payload): SemanticAnalysisException
    {
        $error = $payload['error'] ?? null;
        $code = is_array($error) && is_string($error['code'] ?? null) ? $error['code'] : null;
        if (in_array($statusCode, [401, 403], true)) {
            return new SemanticAnalysisException(MatchingMessage::OPENAI_AUTHENTICATION_FAILED, false);
        }
        if ($statusCode === 429 && $code === 'insufficient_quota') {
            return new SemanticAnalysisException(MatchingMessage::OPENAI_QUOTA_EXCEEDED, false);
        }
        if ($statusCode === 429) {
            return new SemanticAnalysisException(MatchingMessage::OPENAI_RATE_LIMITED, true);
        }
        if ($statusCode >= 500) {
            return new SemanticAnalysisException(MatchingMessage::OPENAI_UNAVAILABLE, true);
        }

        return new SemanticAnalysisException(MatchingMessage::OPENAI_REQUEST_FAILED, false);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['compatibilityScore', 'summary', 'requirements', 'strengths', 'concerns', 'questions'],
            'properties' => [
                'compatibilityScore' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                'summary' => ['type' => 'string'],
                'requirements' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 60,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['category', 'importance', 'label', 'offerEvidence', 'assessment', 'cvEvidence', 'explanation'],
                        'properties' => [
                            'category' => ['type' => 'string', 'enum' => ['TECHNICAL', 'EXPERIENCE', 'RESPONSIBILITY', 'EDUCATION', 'DOMAIN', 'SOFT_SKILL', 'WORKING_CONDITION']],
                            'importance' => ['type' => 'string', 'enum' => ['REQUIRED', 'PREFERRED', 'CONTEXT']],
                            'label' => ['type' => 'string'],
                            'offerEvidence' => ['type' => 'string'],
                            'assessment' => ['type' => 'string', 'enum' => ['MATCH', 'PARTIAL', 'GAP', 'UNKNOWN', 'NOT_APPLICABLE']],
                            'cvEvidence' => ['type' => ['string', 'null']],
                            'explanation' => ['type' => 'string'],
                        ],
                    ],
                ],
                'strengths' => ['type' => 'array', 'maxItems' => 15, 'items' => ['type' => 'string']],
                'concerns' => ['type' => 'array', 'maxItems' => 15, 'items' => ['type' => 'string']],
                'questions' => ['type' => 'array', 'maxItems' => 15, 'items' => ['type' => 'string']],
            ],
        ];
    }
}
