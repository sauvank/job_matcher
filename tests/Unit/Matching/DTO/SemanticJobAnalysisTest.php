<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\DTO;

use App\Matching\DTO\SemanticJobAnalysis;
use PHPUnit\Framework\TestCase;

final class SemanticJobAnalysisTest extends TestCase
{
    public function testItHydratesAndScoresStructuredRequirements(): void
    {
        $analysis = SemanticJobAnalysis::fromArray([
            'compatibilityScore' => 64,
            'summary' => 'Compatibilité partielle.',
            'jobSummary' => 'Conception d’applications PHP/Symfony au sein d’une équipe produit.',
            'keyExpectations' => ['Développer de nouvelles fonctionnalités', 'Participer aux revues de code'],
            'requiredCapacities' => ['PHP 8', 'Symfony', 'Git'],
            'requirements' => [
                $this->requirement('PHP', 'REQUIRED', 'MATCH', 'PHP 8', 'PHP depuis 10 ans'),
                $this->requirement('Angular', 'REQUIRED', 'UNKNOWN', 'Angular 21', null),
                $this->requirement('Kubernetes', 'PREFERRED', 'GAP', 'Kubernetes serait un plus', null),
                $this->requirement('SaaS', 'CONTEXT', 'NOT_APPLICABLE', 'éditeur SaaS', null),
            ],
            'strengths' => ['Expérience PHP confirmée.'],
            'concerns' => ['Angular non confirmé.'],
            'questions' => ['Angular est-il obligatoire ?'],
        ]);

        self::assertCount(4, $analysis->requirements);
        self::assertSame(64, $analysis->compatibilityScore);
        self::assertSame('Conception d’applications PHP/Symfony au sein d’une équipe produit.', $analysis->jobSummary);
        self::assertCount(2, $analysis->keyExpectations);
        self::assertCount(3, $analysis->requiredCapacities);
        self::assertSame($analysis->toArray(), SemanticJobAnalysis::fromArray($analysis->toArray())->toArray());
    }

    public function testItFallsBackForLegacyAnalysisWithoutJobSummaryFields(): void
    {
        $analysis = SemanticJobAnalysis::fromArray([
            'compatibilityScore' => 80,
            'summary' => 'Très bon profil.',
            'requirements' => [
                [
                    'category' => 'RESPONSIBILITY',
                    'importance' => 'REQUIRED',
                    'label' => 'Pilotage technique des sprints',
                    'offerEvidence' => 'Pilotage technique demandé',
                    'assessment' => 'MATCH',
                    'cvEvidence' => 'Lead tech 3 ans',
                    'explanation' => 'Expérience démontrée.',
                ],
                $this->requirement('PHP', 'REQUIRED', 'MATCH', 'PHP 8', 'PHP depuis 10 ans'),
            ],
            'strengths' => ['PHP confirmé.'],
            'concerns' => [],
            'questions' => [],
        ]);

        self::assertNull($analysis->jobSummary);
        self::assertSame(['Pilotage technique des sprints'], $analysis->keyExpectations);
        self::assertSame(['PHP'], $analysis->requiredCapacities);
    }

    /** @return array<string, string|null> */
    private function requirement(string $label, string $importance, string $assessment, string $offerEvidence, ?string $cvEvidence): array
    {
        return [
            'category' => 'TECHNICAL',
            'importance' => $importance,
            'label' => $label,
            'offerEvidence' => $offerEvidence,
            'assessment' => $assessment,
            'cvEvidence' => $cvEvidence,
            'explanation' => 'Explication vérifiable.',
        ];
    }
}
