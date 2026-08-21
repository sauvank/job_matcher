<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\MatchScore;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Entity\JobMatch;
use App\Matching\Service\CvOptimizationReportBuilder;
use PHPUnit\Framework\TestCase;

final class CvOptimizationReportBuilderTest extends TestCase
{
    public function testItSeparatesProvenPartialUnknownAndMissingRequirements(): void
    {
        $profile = new CandidateProfile();
        $firstMatch = $this->match($profile, 1, 'Développeur full stack', [
            $this->requirement('PHP', 'TECHNICAL', 'REQUIRED', 'MATCH', 'PHP sur plusieurs projets'),
            $this->requirement('Docker', 'TECHNICAL', 'REQUIRED', 'PARTIAL', 'Docker mentionné sans détail'),
            $this->requirement('Angular', 'TECHNICAL', 'REQUIRED', 'UNKNOWN', null),
            $this->requirement('PostgreSQL', 'TECHNICAL', 'REQUIRED', 'UNKNOWN', 'SQL et MongoDB sont mentionnés'),
            $this->requirement('PSM I', 'CERTIFICATION', 'REQUIRED', 'GAP', null),
            $this->requirement('Télétravail hybride', 'WORKING_CONDITION', 'PREFERRED', 'UNKNOWN', null),
        ]);
        $secondMatch = $this->match($profile, 2, 'Développeur PHP', [
            $this->requirement('PHP', 'TECHNICAL', 'REQUIRED', 'MATCH', 'Expérience PHP confirmée'),
        ]);

        $report = (new CvOptimizationReportBuilder())->build([$firstMatch, $secondMatch]);

        self::assertSame(2, $report->analyzedOfferCount);
        self::assertSame('PHP', $report->strengthsToHighlight[0]->label);
        self::assertSame(2, $report->strengthsToHighlight[0]->offerCount);
        self::assertSame('Docker', $report->detailsToImprove[0]->label);
        self::assertContains('PostgreSQL', array_map(static fn ($item): string => $item->label, $report->detailsToImprove));
        self::assertSame('Angular', $report->unmentionedToVerify[0]->label);
        self::assertStringContainsString('Si vous le maîtrisez réellement', $report->unmentionedToVerify[0]->recommendation);
        self::assertSame('PSM I', $report->skillsToDevelop[0]->label);
        self::assertStringContainsString('uniquement si elle est obtenue', $report->skillsToDevelop[0]->recommendation);
        self::assertCount(1, $report->unmentionedToVerify);
    }

    /** @param list<array<string, string|null>> $requirements */
    private function match(CandidateProfile $profile, int $offerId, string $title, array $requirements): JobMatch
    {
        $source = new JobSource('Source', 'https://example.test/source-'.$offerId, JobProviderType::FAKE);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'offer-'.$offerId,
            url: 'https://example.test/offer-'.$offerId,
            title: $title,
            company: 'Entreprise',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        (new \ReflectionProperty($offer, 'id'))->setValue($offer, $offerId);

        $score = new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []);
        $match = new JobMatch($profile, $offer, $score);
        $match->completeSemanticAnalysis(SemanticJobAnalysis::fromArray([
            'compatibilityScore' => 60,
            'summary' => 'Analyse de test.',
            'requirements' => $requirements,
            'strengths' => [],
            'concerns' => [],
            'questions' => [],
        ]), 'test');

        return $match;
    }

    /** @return array<string, string|null> */
    private function requirement(string $label, string $category, string $importance, string $assessment, ?string $cvEvidence): array
    {
        return [
            'category' => $category,
            'importance' => $importance,
            'label' => $label,
            'offerEvidence' => $label.' demandé',
            'assessment' => $assessment,
            'cvEvidence' => $cvEvidence,
            'explanation' => 'Explication vérifiable.',
        ];
    }
}
