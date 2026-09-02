<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\DTO\JobApplicationAssistantResult;
use App\Matching\DTO\SemanticJobAnalysis;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class JobApplicationAssistantService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $openAiApiKey = '',
        private string $openAiModel = 'gpt-5.6-luna',
        private string $mode = 'fake',
    ) {
    }

    public function generate(CandidateProfile $profile, JobOffer $offer, ?SemanticJobAnalysis $analysis): JobApplicationAssistantResult
    {
        if ($this->mode === 'openai' && trim($this->openAiApiKey) !== '') {
            try {
                return $this->generateWithOpenAi($profile, $offer, $analysis);
            } catch (\Throwable) {
                // Fallback to deterministic generation on error or timeout
            }
        }

        return $this->generateDeterministic($profile, $offer, $analysis);
    }

    private function generateWithOpenAi(CandidateProfile $profile, JobOffer $offer, ?SemanticJobAnalysis $analysis): JobApplicationAssistantResult
    {
        $candidateSkills = array_map(static fn ($cs) => $cs->getSkill()->getName(), $profile->getCandidateSkills()->toArray());
        $skillsText = implode(', ', array_slice($candidateSkills, 0, 15));
        $strengthsText = $analysis ? implode("\n- ", $analysis->strengths) : '';
        $concernsText = $analysis ? implode("\n- ", $analysis->concerns) : '';

        $prompt = <<<PROMPT
Tu es un expert en recrutement tech et personal branding.
Rédige pour le candidat des documents de candidature personnalisés, percutants, professionnels et sincères (sans inventer d'expérience ni de fausses compétences) pour l'offre suivante :

PROFIL DU CANDIDAT :
- Titre : {$profile->getTitle()}
- Localisation : {$profile->getLocation()}
- Expérience : {$profile->getYearsOfExperience()} ans
- Compétences clés : {$skillsText}
- Points forts identifiés sur l'offre :
- {$strengthsText}
- Points de vigilance / écarts :
- {$concernsText}

OFFRE VISÉE :
- Titre : {$offer->getTitle()}
- Entreprise : {$offer->getCompany()}
- Localisation : {$offer->getLocation()}
- Contrat : {$offer->getContractType()}
- Description : {$offer->getDescription()}

Génère au format JSON strict :
1. "pitch" : Un message d'accroche court (10-15 lignes max) adapté pour LinkedIn ou un email direct au recruteur. Ton professionnel, courtois, mettant en valeur l'adéquation technique et l'intérêt pour le projet de l'entreprise.
2. "coverLetter" : Une lettre de motivation complète et structurée (Vous / Moi / Nous) valorisant les réalisations pertinentes et la valeur ajoutée apportée à l'équipe.
3. "followUpMessage" : Un message de relance poli et concis à envoyer 7 à 10 jours après candidature si aucune réponse n'a été reçue.
4. "interviewQuestions" : Une liste de 3 à 5 questions probables que le recruteur va poser en entretien (axées sur les exigences clés et les points de vigilance du CV), avec pour chacune :
   - "question" : L'intitulé de la question
   - "context" : Pourquoi le recruteur pose cette question
   - "suggestedAnswer" : Conseils et trame d'argumentation recommandée pour le candidat
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->openAiModel,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es un assistant expert en recrutement francophone.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ],
            'timeout' => 60,
        ]);

        $data = $response->toArray();
        $content = json_decode((string) ($data['choices'][0]['message']['content'] ?? '{}'), true);

        if (!is_array($content) || !isset($content['pitch'], $content['coverLetter'], $content['followUpMessage'])) {
            return $this->generateDeterministic($profile, $offer, $analysis);
        }

        $interviewQuestions = [];
        if (isset($content['interviewQuestions']) && is_array($content['interviewQuestions'])) {
            foreach ($content['interviewQuestions'] as $q) {
                if (is_array($q) && isset($q['question'], $q['context'], $q['suggestedAnswer'])) {
                    $interviewQuestions[] = [
                        'question' => (string) $q['question'],
                        'context' => (string) $q['context'],
                        'suggestedAnswer' => (string) $q['suggestedAnswer'],
                    ];
                }
            }
        }

        return new JobApplicationAssistantResult(
            pitch: (string) $content['pitch'],
            coverLetter: (string) $content['coverLetter'],
            followUpMessage: (string) $content['followUpMessage'],
            interviewQuestions: $interviewQuestions,
        );
    }

    public function generateDeterministic(CandidateProfile $profile, JobOffer $offer, ?SemanticJobAnalysis $analysis): JobApplicationAssistantResult
    {
        $candidateTitle = $profile->getTitle() ?? 'Développeur';
        $company = $offer->getCompany() ?? 'votre entreprise';
        $jobTitle = $offer->getTitle();
        $experience = $profile->getYearsOfExperience() ?? 3;

        $candidateSkills = array_map(static fn ($cs) => $cs->getSkill()->getName(), $profile->getCandidateSkills()->toArray());
        $mainSkills = array_slice($candidateSkills, 0, 4);
        $skillsPhrase = $mainSkills !== [] ? implode(', ', $mainSkills) : 'l’écosystème web moderne';

        // 1. Pitch
        $pitch = <<<TEXT
Bonjour,

Je me permets de vous contacter car j'ai découvert avec beaucoup d'intérêt votre opportunité de {$jobTitle} au sein de {$company}.

Fort d'une expérience de {$experience} ans en tant que {$candidateTitle}, j'ai développé une solide maîtrise technique sur {$skillsPhrase}. Les enjeux décrits dans votre annonce correspondent particulièrement à mes compétences et à ce que j'aime réaliser au quotidien.

Je serais ravi d'échanger avec vous quelques minutes lors d'un bref appel pour vous présenter mon parcours et discuter de vos priorités actuelles.

Bien cordialement,
TEXT;

        // 2. Lettre de motivation
        $strengthsList = '';
        if ($analysis && $analysis->strengths !== []) {
            $strengthsList = "\n".implode("\n", array_map(static fn ($s) => '- '.$s, array_slice($analysis->strengths, 0, 3)))."\n";
        }

        $coverLetter = <<<TEXT
Madame, Monsieur,

C’est avec un vif enthousiasme que je vous adresse ma candidature pour le poste de {$jobTitle} au sein de {$company}.

Votre annonce a immédiatement retenu mon attention par la qualité des défis techniques proposés et le contexte dynamique de votre équipe. En tant que {$candidateTitle} cumulant {$experience} ans d'expérience, j'ai eu l'opportunité de concevoir, déployer et maintenir des applications robustes et scalables, notamment avec {$skillsPhrase}.
{$strengthsList}
Rejoindre {$company} représenterait pour moi l'opportunité de mettre à profit ma rigueur technique et ma culture des bonnes pratiques, tout en m'investissant pleinement dans la réussite de vos projets.

Je reste à votre entière disposition pour convenir d'un entretien à votre convenance.

En vous remerciant pour l'attention portée à ma candidature, je vous prie d'agréer, Madame, Monsieur, l'expression de mes salutations distinguées.
TEXT;

        // 3. Message de relance
        $followUpMessage = <<<TEXT
Bonjour,

Je me permets de revenir vers vous concernant ma candidature transmise récemment pour le poste de {$jobTitle}.

Toujours très enthousiaste à l'idée d'intégrer {$company} et de mettre mon expertise au service de votre équipe, je souhaitais m'assurer de la bonne réception de mon dossier et savoir si le processus de sélection suit son cours.

Je reste disponible pour tout renseignement complémentaire ou pour un premier échange téléphonique.

Bien cordialement,
TEXT;

        // 4. Questions d'entretien
        $interviewQuestions = [];
        if ($analysis && $analysis->questions !== []) {
            foreach (array_slice($analysis->questions, 0, 4) as $q) {
                $interviewQuestions[] = [
                    'question' => $q,
                    'context' => 'Point d\'attention ou validation technique soulevé lors de l\'analyse de l\'annonce.',
                    'suggestedAnswer' => 'Valorisez vos réalisations concrètes en illustrant par un exemple de projet précédent où vous avez appliqué cette technologie ou résolu un problème similaire.',
                ];
            }
        } else {
            $interviewQuestions = [
                [
                    'question' => 'Pouvez-vous me parler d’un projet marquant où vous avez utilisé '.($mainSkills[0] ?? 'votre stack principale').' ?',
                    'context' => 'Évaluation de la profondeur technique et de la capacité à expliquer des choix d\'architecture.',
                    'suggestedAnswer' => 'Structurez votre réponse selon la méthode STAR (Situation, Tâche, Action, Résultat) en précisant votre rôle exact et les bénéfices pour l\'équipe.',
                ],
                [
                    'question' => 'Comment assurez-vous la qualité logicielle et la robustesse de votre code ?',
                    'context' => 'Vérification de la culture des tests, de la revue de code et du respect des standards de développement.',
                    'suggestedAnswer' => 'Évoquez les tests automatisés (unitaires/fonctionnels), le respect des principes SOLID, l\'intégration continue (CI/CD) et les bonnes pratiques de refactorisation.',
                ],
                [
                    'question' => 'Qu’est-ce qui vous attire particulièrement dans le poste et chez '.$company.' ?',
                    'context' => 'Test de motivation et d\'alignement avec la vision de l\'entreprise.',
                    'suggestedAnswer' => 'Mettez en avant les défis techniques du produit, les technologies utilisées et la culture d\'équipe mise en valeur dans l\'annonce.',
                ],
            ];
        }

        return new JobApplicationAssistantResult(
            pitch: trim($pitch),
            coverLetter: trim($coverLetter),
            followUpMessage: trim($followUpMessage),
            interviewQuestions: $interviewQuestions,
        );
    }
}
