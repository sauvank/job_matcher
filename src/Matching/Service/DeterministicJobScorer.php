<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Enum\RemotePolicy;
use App\Job\Entity\JobOffer;
use App\Job\Service\TechnicalRequirementExtractor;
use App\Matching\DTO\MatchReason;
use App\Matching\DTO\MatchScore;
use App\Matching\Translation\MatchingMessage;

final readonly class DeterministicJobScorer
{
    private const SCORE_UNKNOWN = 50;

    /** @param array{stack: int, experience: int, salary: int, location: int, contract: int, remote: int, backend: int} $weights */
    public function __construct(
        private array $weights,
        private TechnicalRequirementExtractor $requirementExtractor,
    ) {
    }

    public function score(CandidateProfile $profile, JobOffer $offer): MatchScore
    {
        $strengths = [];
        $gaps = [];
        $unknowns = [];
        $haystack = $this->normalize($offer->getTitle().' '.($offer->getDescription() ?? ''));

        $stackScore = $this->scoreStack($profile, $offer, $strengths, $gaps, $unknowns);
        $experienceScore = $this->scoreExperience($profile, $offer, $strengths, $gaps, $unknowns);
        $salaryScore = $this->scoreSalary($profile, $offer, $strengths, $gaps, $unknowns);
        $locationScore = $this->scoreLocation($profile, $offer, $strengths, $gaps, $unknowns);
        $contractScore = $this->scoreContract($profile, $offer, $strengths, $gaps, $unknowns);
        $remoteScore = $this->scoreRemote($profile, $offer, $strengths, $gaps, $unknowns);
        $backendScore = $this->scoreBackend($haystack, $strengths, $unknowns);

        $scores = [
            'stack' => $stackScore,
            'experience' => $experienceScore,
            'salary' => $salaryScore,
            'location' => $locationScore,
            'contract' => $contractScore,
            'remote' => $remoteScore,
            'backend' => $backendScore,
        ];
        $weightedTotal = 0;
        $weightTotal = 0;
        foreach ($scores as $criterion => $score) {
            $weight = $this->weights[$criterion];
            $weightedTotal += $score * $weight;
            $weightTotal += $weight;
        }

        $deterministicScore = $weightTotal > 0 ? (int) round($weightedTotal / $weightTotal) : 0;

        return new MatchScore(
            globalScore: $deterministicScore,
            hardCriteriaScore: $deterministicScore,
            stackScore: $stackScore,
            experienceScore: $experienceScore,
            salaryScore: $salaryScore,
            locationScore: $locationScore,
            contractScore: $contractScore,
            remoteScore: $remoteScore,
            backendScore: $backendScore,
            strengths: $strengths,
            gaps: $gaps,
            blockers: [],
            unknowns: $unknowns,
        );
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreStack(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        if ($profile->getCandidateSkills()->isEmpty()) {
            $unknowns[] = new MatchReason(MatchingMessage::SKILLS_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        $requiredSkills = $this->requirementExtractor->extract($offer);
        if ($requiredSkills === []) {
            $unknowns[] = new MatchReason(MatchingMessage::REQUIRED_SKILLS_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        $matched = 0;
        foreach ($requiredSkills as $requiredSkill) {
            if ($this->candidateHasSkill($profile, $requiredSkill)) {
                ++$matched;
                $strengths[] = new MatchReason(MatchingMessage::SKILL_PRESENT, ['%skill%' => $requiredSkill]);
            } else {
                $gaps[] = new MatchReason(MatchingMessage::REQUIRED_SKILL_MISSING, ['%skill%' => $requiredSkill]);
            }
        }

        return (int) round(100 * $matched / count($requiredSkills));
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreExperience(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        $candidateYears = $profile->getYearsOfExperience();
        $requiredYears = $offer->getYearsOfExperience();
        if ($candidateYears === null || $requiredYears === null) {
            $unknowns[] = new MatchReason(MatchingMessage::EXPERIENCE_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        if ($candidateYears >= $requiredYears) {
            $strengths[] = new MatchReason(MatchingMessage::EXPERIENCE_COMPATIBLE, [
                '%candidate%' => $candidateYears,
                '%required%' => $requiredYears,
            ]);

            return 100;
        }

        $gaps[] = new MatchReason(MatchingMessage::EXPERIENCE_INSUFFICIENT, [
            '%candidate%' => $candidateYears,
            '%required%' => $requiredYears,
        ]);

        return $requiredYears > 0 ? (int) round(100 * $candidateYears / $requiredYears) : 100;
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreSalary(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        $expected = $profile->getMinimumSalary();
        $offeredMaximum = $offer->getMaximumSalary() ?? $offer->getMinimumSalary();
        if ($expected === null || $offeredMaximum === null) {
            $unknowns[] = new MatchReason(MatchingMessage::SALARY_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        if ($offeredMaximum >= $expected) {
            $strengths[] = new MatchReason(MatchingMessage::SALARY_COMPATIBLE, [
                '%expected%' => $expected,
                '%offered%' => $offeredMaximum,
            ]);

            return 100;
        }

        $gaps[] = new MatchReason(MatchingMessage::SALARY_BELOW, [
            '%expected%' => $expected,
            '%offered%' => $offeredMaximum,
        ]);

        return $expected > 0 ? (int) round(100 * $offeredMaximum / $expected) : 100;
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreLocation(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        $candidateLocation = $profile->getLocation();
        $offerLocation = $offer->getLocation();
        if ($candidateLocation === null || $offerLocation === null) {
            $unknowns[] = new MatchReason(MatchingMessage::LOCATION_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        if ($this->locationsOverlap($candidateLocation, $offerLocation)) {
            $strengths[] = new MatchReason(MatchingMessage::LOCATION_COMPATIBLE, ['%location%' => $offerLocation]);

            return 100;
        }

        if ($offer->getRemotePolicy() !== null) {
            $strengths[] = new MatchReason(MatchingMessage::LOCATION_REMOTE, ['%location%' => $offerLocation]);

            return 80;
        }

        $gaps[] = new MatchReason(MatchingMessage::LOCATION_MISMATCH, [
            '%candidate%' => $candidateLocation,
            '%offer%' => $offerLocation,
        ]);

        return 20;
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreContract(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        $preferences = array_map('strtoupper', $profile->getPreferredContractTypes());
        $contract = $offer->getContractType();
        if ($preferences === [] || $contract === null) {
            $unknowns[] = new MatchReason(MatchingMessage::CONTRACT_NEUTRAL);

            return self::SCORE_UNKNOWN;
        }

        $normalizedContract = mb_strtoupper($contract);
        $matches = false;
        foreach ($preferences as $preference) {
            $pref = mb_strtoupper($preference);
            if ($pref === $normalizedContract) {
                $matches = true;
                break;
            }
            if ($pref === 'FREELANCE' && (str_contains($normalizedContract, 'FREELANCE') || str_contains($normalizedContract, 'INDÉPENDANT') || str_contains($normalizedContract, 'INDEPENDANT'))) {
                $matches = true;
                break;
            }
            if (($pref === 'APPRENTICESHIP' || $pref === 'ALTERNANCE') && (str_contains($normalizedContract, 'APPRENT') || str_contains($normalizedContract, 'ALTERN') || str_contains($normalizedContract, 'CONTRAT PRO'))) {
                $matches = true;
                break;
            }
            if (($pref === 'INTERNSHIP' || $pref === 'STAGE') && (str_contains($normalizedContract, 'STAGE') || str_contains($normalizedContract, 'INTERN'))) {
                $matches = true;
                break;
            }
            if ($pref === 'CDI' && (str_contains($normalizedContract, 'CDI') || str_contains($normalizedContract, 'FULL_TIME') || str_contains($normalizedContract, 'PERMANENT'))) {
                $matches = true;
                break;
            }
            if ($pref === 'CDD' && (str_contains($normalizedContract, 'CDD') || str_contains($normalizedContract, 'TEMPORARY'))) {
                $matches = true;
                break;
            }
        }

        if ($matches) {
            $strengths[] = new MatchReason(MatchingMessage::CONTRACT_COMPATIBLE, ['%contract%' => $contract]);

            return 100;
        }

        $gaps[] = new MatchReason(MatchingMessage::CONTRACT_MISMATCH, ['%contract%' => $contract]);

        return 0;
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $unknowns
     */
    private function scoreRemote(CandidateProfile $profile, JobOffer $offer, array &$strengths, array &$gaps, array &$unknowns): int
    {
        $preference = $profile->getPreferredRemotePolicy();
        if (in_array($preference, [RemotePolicy::UNKNOWN, RemotePolicy::FLEXIBLE], true)) {
            $unknowns[] = new MatchReason(MatchingMessage::REMOTE_UNKNOWN);

            return self::SCORE_UNKNOWN;
        }

        $remoteAvailable = $offer->getRemotePolicy() !== null;
        if ($preference === RemotePolicy::ON_SITE || $remoteAvailable) {
            $strengths[] = new MatchReason(MatchingMessage::REMOTE_COMPATIBLE);

            return 100;
        }

        $gaps[] = new MatchReason(MatchingMessage::REMOTE_MISMATCH);

        return 0;
    }

    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $unknowns
     */
    private function scoreBackend(string $haystack, array &$strengths, array &$unknowns): int
    {
        foreach (['backend', 'back end', 'api', 'symfony', 'php'] as $signal) {
            if ($this->contains($haystack, $signal)) {
                $strengths[] = new MatchReason(MatchingMessage::BACKEND_CONFIRMED);

                return 100;
            }
        }

        $unknowns[] = new MatchReason(MatchingMessage::BACKEND_UNCERTAIN);

        return self::SCORE_UNKNOWN;
    }

    private function contains(string $haystack, string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && str_contains(' '.$haystack.' ', ' '.$needle.' ');
    }

    private function skillIsMentioned(string $haystack, string $name, string $normalizedName): bool
    {
        $aliases = [$name, $normalizedName];
        if (preg_match('/^([^()]+)\s*\(([^)]+)\)$/u', $name, $matches) === 1) {
            $aliases[] = trim($matches[1]);
            foreach (preg_split('/\s*[,;]\s*/u', $matches[2]) ?: [] as $alias) {
                $aliases[] = $alias;
            }
        }

        return array_any($aliases, fn (string $alias): bool => $this->contains($haystack, $alias));
    }

    private function candidateHasSkill(CandidateProfile $profile, string $requiredSkill): bool
    {
        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            $skill = $candidateSkill->getSkill();
            $candidateSkillText = $this->normalize($skill->getName().' '.$skill->getNormalizedName());
            if ($this->skillIsMentioned($candidateSkillText, $requiredSkill, $requiredSkill)) {
                return true;
            }
        }

        return false;
    }

    private function locationsOverlap(string $candidateLocation, string $offerLocation): bool
    {
        $candidateWords = array_filter(explode(' ', $this->normalize($candidateLocation)), static fn (string $word): bool => mb_strlen($word) >= 3);
        $offerWords = array_filter(explode(' ', $this->normalize($offerLocation)), static fn (string $word): bool => mb_strlen($word) >= 3);

        return array_intersect($candidateWords, $offerWords) !== [];
    }

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return is_string($normalized) ? trim($normalized) : '';
    }
}
