<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Matching\DTO\CvOptimizationItem;
use App\Matching\DTO\CvOptimizationReport;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;

final readonly class CvOptimizationReportBuilder
{
    private const int ITEM_LIMIT = 8;

    /** @param list<JobMatch> $matches */
    public function build(array $matches): CvOptimizationReport
    {
        /** @var array<string, CvOptimizationAccumulator> $requirements */
        $requirements = [];
        /** @var array<string, true> $seenOfferUrls */
        $seenOfferUrls = [];
        $analyzedOfferCount = 0;

        foreach ($matches as $match) {
            $rawAnalysis = $match->getSemanticAnalysis();
            $offer = $match->getJobOffer();
            $offerId = $offer->getId();
            if ($rawAnalysis === null || $offerId === null) {
                continue;
            }
            $offerUrl = $offer->getUrl();
            if (isset($seenOfferUrls[$offerUrl])) {
                continue;
            }

            try {
                $analysis = SemanticJobAnalysis::fromArray($rawAnalysis);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $seenOfferUrls[$offerUrl] = true;
            ++$analyzedOfferCount;

            foreach ($analysis->requirements as $requirement) {
                if ($requirement->importance->value === 'CONTEXT'
                    || $requirement->assessment === RequirementAssessment::NOT_APPLICABLE
                    || $requirement->category === RequirementCategory::WORKING_CONDITION) {
                    continue;
                }

                $key = $this->normalize($requirement->label);
                if ($key === '') {
                    continue;
                }
                $requirements[$key] ??= new CvOptimizationAccumulator(
                    $this->displayLabel($key, $requirement->label),
                    $requirement->category,
                    $offerId,
                    $offer->getTitle(),
                );
                $requirements[$key]->add($requirement, $offerId);
            }
        }

        $strengths = [];
        $improvements = [];
        $unmentioned = [];
        $development = [];
        foreach ($requirements as $requirement) {
            $item = $this->createItem($requirement);
            if ($requirement->has(RequirementAssessment::PARTIAL)
                || ($requirement->has(RequirementAssessment::UNKNOWN) && $requirement->cvEvidence() !== null)) {
                $improvements[] = $item;
            } elseif ($requirement->has(RequirementAssessment::MATCH)) {
                $strengths[] = $item;
            } elseif ($requirement->has(RequirementAssessment::UNKNOWN)) {
                $unmentioned[] = $item;
            } else {
                $development[] = $item;
            }
        }

        return new CvOptimizationReport(
            $analyzedOfferCount,
            $this->rank($strengths),
            $this->rank($improvements),
            $this->rank($unmentioned),
            $this->rank($development),
        );
    }

    private function createItem(CvOptimizationAccumulator $requirement): CvOptimizationItem
    {
        $type = $requirement->has(RequirementAssessment::PARTIAL) ? 'partial'
            : ($requirement->has(RequirementAssessment::MATCH) ? 'match'
                : ($requirement->has(RequirementAssessment::UNKNOWN) ? 'unknown' : 'gap'));

        return new CvOptimizationItem(
            $requirement->label,
            $requirement->category,
            $requirement->offerCount(),
            $requirement->requiredCount(),
            $requirement->cvEvidence(),
            $this->recommendation($requirement->label, $requirement->category, $type),
            $requirement->exampleOfferId,
            $requirement->exampleOfferTitle,
            ($requirement->requiredCount() * 10) + ($requirement->offerCount() * 4),
        );
    }

    private function recommendation(string $label, RequirementCategory $category, string $type): string
    {
        if ($type === 'unknown') {
            return sprintf('Le CV ne permet pas de confirmer « %s ». Si vous le maîtrisez réellement, ajoutez un exemple précis ; sinon, ne le revendiquez pas.', $label);
        }
        if ($type === 'gap') {
            return $category === RequirementCategory::CERTIFICATION
                ? sprintf('La certification « %s » est demandée. Ajoutez-la uniquement si elle est obtenue ; sinon, envisagez de la préparer.', $label)
                : sprintf('« %s » ressort comme un écart. À acquérir ou pratiquer avant de l’ajouter au CV.', $label);
        }
        if ($type === 'partial') {
            return sprintf('Précisez pour « %s » le contexte, votre niveau, la durée et si possible un résultat concret.', $label);
        }

        return sprintf('Rendez « %s » plus visible dans l’accroche ou une expérience récente, idéalement avec un résultat mesurable.', $label);
    }

    /**
     * @param list<CvOptimizationItem> $items
     *
     * @return list<CvOptimizationItem>
     */
    private function rank(array $items): array
    {
        usort($items, static fn (CvOptimizationItem $left, CvOptimizationItem $right): int => $right->relevanceScore <=> $left->relevanceScore ?: strcasecmp($left->label, $right->label));

        return array_slice($items, 0, self::ITEM_LIMIT);
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/[^\p{L}\p{N}+#.]+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (preg_match('/^(framework )?symfony(?: \d+(?:\.\d+)?)?$/u', $normalized) === 1) {
            return 'symfony';
        }
        if (preg_match('/^(excellente? maîtrise de |maîtrise de )?php(?: \d+\+?)?$/u', $normalized) === 1) {
            return 'php';
        }
        if (str_contains($normalized, 'intégration continue') || preg_match('/^(pipelines? )?ci cd$/u', $normalized) === 1) {
            return 'ci cd';
        }

        return $normalized;
    }

    private function displayLabel(string $normalized, string $original): string
    {
        return match ($normalized) {
            'php' => 'PHP',
            'symfony' => 'Symfony',
            'ci cd' => 'CI/CD',
            default => $original,
        };
    }
}
