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
        self::assertSame($analysis->toArray(), SemanticJobAnalysis::fromArray($analysis->toArray())->toArray());
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
