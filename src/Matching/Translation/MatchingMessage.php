<?php

declare(strict_types=1);

namespace App\Matching\Translation;

final class MatchingMessage
{
    public const SKILL_PRESENT = 'matching.reason.skill_present';
    public const SKILL_MISSING = 'matching.reason.skill_missing';
    public const SKILLS_UNKNOWN = 'matching.reason.skills_unknown';
    public const REQUIRED_SKILL_MISSING = 'matching.reason.required_skill_missing';
    public const REQUIRED_SKILLS_UNKNOWN = 'matching.reason.required_skills_unknown';
    public const EXPERIENCE_COMPATIBLE = 'matching.reason.experience_compatible';
    public const EXPERIENCE_INSUFFICIENT = 'matching.reason.experience_insufficient';
    public const EXPERIENCE_UNKNOWN = 'matching.reason.experience_unknown';
    public const SALARY_COMPATIBLE = 'matching.reason.salary_compatible';
    public const SALARY_BELOW = 'matching.reason.salary_below';
    public const SALARY_UNKNOWN = 'matching.reason.salary_unknown';
    public const LOCATION_COMPATIBLE = 'matching.reason.location_compatible';
    public const LOCATION_REMOTE = 'matching.reason.location_remote';
    public const LOCATION_MISMATCH = 'matching.reason.location_mismatch';
    public const LOCATION_UNKNOWN = 'matching.reason.location_unknown';
    public const CONTRACT_COMPATIBLE = 'matching.reason.contract_compatible';
    public const CONTRACT_MISMATCH = 'matching.reason.contract_mismatch';
    public const CONTRACT_NEUTRAL = 'matching.reason.contract_neutral';
    public const REMOTE_COMPATIBLE = 'matching.reason.remote_compatible';
    public const REMOTE_MISMATCH = 'matching.reason.remote_mismatch';
    public const REMOTE_UNKNOWN = 'matching.reason.remote_unknown';
    public const BACKEND_CONFIRMED = 'matching.reason.backend_confirmed';
    public const BACKEND_UNCERTAIN = 'matching.reason.backend_uncertain';
    public const NO_BLOCKER = 'matching.reason.no_blocker';

    private function __construct()
    {
    }
}
