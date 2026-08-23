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

final readonly class GeminiJobSemanticAnalyzer implements JobSemanticAnalyzerInterface
{
    private const INSTRUCTIONS = <<<'PROMPT'
        Tu es un analyste de recrutement rigoureux. Extrais TOUTES les exigences et caractéristiques significatives de l’annonce : technologies, versions, méthodes, expérience, formation, certifications, responsabilités, séniorité, domaine, qualités humaines et conditions de travail. Ne te limite jamais au champ skills du fournisseur. Toute certification, habilitation ou autorisation professionnelle doit constituer une exigence CERTIFICATION distincte.

        Pour chaque exigence, cite un court extrait exact de l’annonce dans offerEvidence. Classe REQUIRED seulement si le texte l’impose clairement, PREFERRED si le texte dit idéalement/souhaité/serait un plus, CONTEXT si ce n’est pas une exigence candidat. Une certification formulée comme obligatoire, requise, exigée ou indispensable est toujours REQUIRED. Compare ensuite au CV : MATCH exige une preuve explicite dans le CV, PARTIAL une preuve proche mais incomplète, GAP une contradiction ou une insuffisance explicite, UNKNOWN si le CV ne permet pas de conclure. L’absence de mention d’une certification dans le CV est UNKNOWN, jamais MATCH et jamais une preuve que le candidat ne la possède pas. N’invente jamais une compétence à partir d’une compétence voisine. Pour UNKNOWN ou GAP sans preuve CV, cvEvidence doit être null.

        Évalue précisément les formations sans surinterpréter leurs intitulés. Une Licence, un Bachelor ou un titre RNCP de niveau 6 en développement peut satisfaire une demande générique de « formation supérieure en informatique ». Si l’annonce impose Bac+5, Master ou diplôme d’ingénieur, un Bac+3 seul est PARTIAL ou GAP selon la formulation. Un certificat professionnel sans niveau reconnu ne prouve pas à lui seul une formation supérieure. Ne compte jamais séparément un diplôme et son titre RNCP lorsqu’ils peuvent désigner le même cursus, et ne déduis pas un nombre de diplômes qui n’est pas certain.

        Retourne compatibilityScore, un entier de 0 à 100 représentant la compatibilité globale. Pondère fortement les exigences REQUIRED et plus faiblement les PREFERRED. Un UNKNOWN sur une préférence reste neutre ou faiblement pénalisant, mais un UNKNOWN sur une exigence REQUIRED constitue une incertitude importante et doit réduire nettement le score. Une certification REQUIRED sans preuve explicite dans le CV est un point bloquant potentiel : place-la dans concerns, propose une question de vérification et plafonne le score global à 69. S’il manque la preuve de plusieurs certifications ou habilitations REQUIRED, plafonne le score à 49. Une certification PREFERRED non mentionnée ne déclenche aucun plafond. Le score doit être cohérent avec les verdicts et expliqué par summary, strengths et concerns. Réponds en français, déduplique les exigences, sépare les technologies distinctes et respecte strictement le schéma JSON.
        PROMPT;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private string $model,
    ) {
    }

    public function analyze(CandidateProfile $profile, JobOffer $offer): SemanticJobAnalysis
    {
        if (trim($this->apiKey) === '') {
            throw new SemanticAnalysisException(MatchingMessage::MISSING_GEMINI_KEY, false);
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
                                ['text' => $this->input($profile, $offer)],
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
                'timeout' => 90,
            ]);
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TimeoutExceptionInterface $exception) {
            throw new SemanticAnalysisException(MatchingMessage::GEMINI_TIMEOUT, true, $exception);
        } catch (TransportExceptionInterface $exception) {
            throw new SemanticAnalysisException(MatchingMessage::GEMINI_CONNECTION_FAILED, true, $exception);
        }

        $decodedResponse = json_decode($body, true);
        $payload = is_array($decodedResponse) ? $decodedResponse : [];
        if ($statusCode >= 400) {
            throw self::httpException($statusCode, $payload);
        }

        $outputText = self::outputText($payload);

        try {
            $data = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SemanticAnalysisException(MatchingMessage::INVALID_GEMINI_JSON, true, $exception);
        }
        if (!is_array($data)) {
            throw new SemanticAnalysisException(MatchingMessage::INVALID_SEMANTIC_ANALYSIS, true);
        }

        /* @var array<string, mixed> $data */
        return SemanticJobAnalysis::fromArray($data);
    }

    public function name(): string
    {
        return 'gemini:'.$this->model;
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
        $candidates = $payload['candidates'] ?? null;
        if (!is_array($candidates) || !isset($candidates[0]) || !is_array($candidates[0])) {
            throw new SemanticAnalysisException(MatchingMessage::MISSING_GEMINI_OUTPUT, true);
        }

        $parts = $candidates[0]['content']['parts'] ?? null;
        if (!is_array($parts) || !isset($parts[0]['text']) || !is_string($parts[0]['text']) || trim($parts[0]['text']) === '') {
            throw new SemanticAnalysisException(MatchingMessage::MISSING_GEMINI_OUTPUT, true);
        }

        return $parts[0]['text'];
    }

    /** @param array<string, mixed> $payload */
    private static function httpException(int $statusCode, array $payload): SemanticAnalysisException
    {
        $error = $payload['error'] ?? null;
        $status = is_array($error) && is_string($error['status'] ?? null) ? $error['status'] : null;
        $message = is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : '';

        if (in_array($statusCode, [401, 403], true) || ($statusCode === 400 && str_contains(strtolower($message), 'api key'))) {
            return new SemanticAnalysisException(MatchingMessage::GEMINI_AUTHENTICATION_FAILED, false);
        }

        if ($statusCode === 429) {
            if ($status === 'RESOURCE_EXHAUSTED' && str_contains(strtolower($message), 'quota')) {
                return new SemanticAnalysisException(MatchingMessage::GEMINI_QUOTA_EXCEEDED, false);
            }

            return new SemanticAnalysisException(MatchingMessage::GEMINI_RATE_LIMITED, true);
        }

        if ($statusCode >= 500) {
            return new SemanticAnalysisException(MatchingMessage::GEMINI_UNAVAILABLE, true);
        }

        return new SemanticAnalysisException(MatchingMessage::GEMINI_REQUEST_FAILED, false);
    }

    /** @return array<string, mixed> */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'compatibilityScore' => ['type' => 'integer'],
                'summary' => ['type' => 'string'],
                'requirements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => ['type' => 'string', 'enum' => ['TECHNICAL', 'EXPERIENCE', 'RESPONSIBILITY', 'EDUCATION', 'CERTIFICATION', 'DOMAIN', 'SOFT_SKILL', 'WORKING_CONDITION']],
                            'importance' => ['type' => 'string', 'enum' => ['REQUIRED', 'PREFERRED', 'CONTEXT']],
                            'label' => ['type' => 'string'],
                            'offerEvidence' => ['type' => 'string'],
                            'assessment' => ['type' => 'string', 'enum' => ['MATCH', 'PARTIAL', 'GAP', 'UNKNOWN', 'NOT_APPLICABLE']],
                            'cvEvidence' => ['type' => 'string', 'nullable' => true],
                            'explanation' => ['type' => 'string'],
                        ],
                        'required' => ['category', 'importance', 'label', 'offerEvidence', 'assessment', 'explanation'],
                    ],
                ],
                'strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                'concerns' => ['type' => 'array', 'items' => ['type' => 'string']],
                'questions' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['compatibilityScore', 'summary', 'requirements', 'strengths', 'concerns', 'questions'],
        ];
    }
}
