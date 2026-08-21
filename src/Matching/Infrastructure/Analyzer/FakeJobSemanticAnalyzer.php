<?php

declare(strict_types=1);

namespace App\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Job\Service\TechnicalRequirementExtractor;
use App\Matching\Application\Analyzer\JobSemanticAnalyzerInterface;
use App\Matching\DTO\AnalyzedRequirement;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;

final readonly class FakeJobSemanticAnalyzer implements JobSemanticAnalyzerInterface
{
    public function __construct(private TechnicalRequirementExtractor $requirementExtractor)
    {
    }

    public function analyze(CandidateProfile $profile, JobOffer $offer): SemanticJobAnalysis
    {
        $requirements = array_map(
            static fn (string $skill): AnalyzedRequirement => new AnalyzedRequirement(
                RequirementCategory::TECHNICAL,
                RequirementImportance::REQUIRED,
                $skill,
                $skill,
                RequirementAssessment::UNKNOWN,
                null,
                'Le mode local ne peut pas établir une correspondance sémantique fiable.',
            ),
            $this->requirementExtractor->extract($offer),
        );
        if ($requirements === []) {
            $requirements[] = new AnalyzedRequirement(
                RequirementCategory::TECHNICAL,
                RequirementImportance::CONTEXT,
                'Exigences non structurées',
                $offer->getTitle(),
                RequirementAssessment::UNKNOWN,
                null,
                'Configurez OpenAI pour analyser le texte complet.',
            );
        }

        return new SemanticJobAnalysis(
            50,
            'Analyse locale limitée. Activez OpenAI pour obtenir un compte rendu exhaustif avec preuves.',
            $requirements,
            [],
            [],
            ['Quelles exigences sont réellement obligatoires pour ce poste ?'],
        );
    }

    public function name(): string
    {
        return 'fake';
    }
}
