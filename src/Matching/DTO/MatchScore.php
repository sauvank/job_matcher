<?php

declare(strict_types=1);

namespace App\Matching\DTO;

final readonly class MatchScore
{
    /**
     * @param list<MatchReason> $strengths
     * @param list<MatchReason> $gaps
     * @param list<MatchReason> $blockers
     * @param list<MatchReason> $unknowns
     */
    public function __construct(
        public int $globalScore,
        public int $hardCriteriaScore,
        public int $stackScore,
        public int $experienceScore,
        public int $salaryScore,
        public int $locationScore,
        public int $contractScore,
        public int $remoteScore,
        public int $backendScore,
        public array $strengths,
        public array $gaps,
        public array $blockers,
        public array $unknowns,
    ) {
    }

    /** @return array<string, list<array{key: string, parameters: array<string, int|string>}>> */
    public function explanation(): array
    {
        return [
            'strengths' => array_map(static fn (MatchReason $reason): array => $reason->toArray(), $this->strengths),
            'gaps' => array_map(static fn (MatchReason $reason): array => $reason->toArray(), $this->gaps),
            'blockers' => array_map(static fn (MatchReason $reason): array => $reason->toArray(), $this->blockers),
            'unknowns' => array_map(static fn (MatchReason $reason): array => $reason->toArray(), $this->unknowns),
        ];
    }
}
