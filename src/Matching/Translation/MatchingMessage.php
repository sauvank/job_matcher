<?php

declare(strict_types=1);

namespace App\Matching\Translation;

final class MatchingMessage
{
    public const MATCH_NOT_FOUND = 'matching.analysis.match_not_found';
    public const APPLICATION_STATUS_UPDATED = 'matching.status.updated';
    public const SEMANTIC_ANALYSIS_QUEUED = 'matching.analysis.queued';
    public const SEMANTIC_ANALYSIS_FAILED = 'matching.analysis.failed';
    public const SEMANTIC_ANALYSIS_TIMEOUT = 'matching.analysis.timeout';
    public const INVALID_SEMANTIC_ANALYSIS = 'matching.analysis.invalid';
    public const UNKNOWN_SEMANTIC_ANALYZER = 'matching.analysis.unknown_analyzer';
    public const MISSING_OPENAI_KEY = 'matching.analysis.missing_openai_key';
    public const INVALID_OPENAI_JSON = 'matching.analysis.invalid_openai_json';
    public const MISSING_OPENAI_OUTPUT = 'matching.analysis.missing_openai_output';
    public const OPENAI_AUTHENTICATION_FAILED = 'matching.analysis.openai_authentication_failed';
    public const OPENAI_QUOTA_EXCEEDED = 'matching.analysis.openai_quota_exceeded';
    public const OPENAI_RATE_LIMITED = 'matching.analysis.openai_rate_limited';
    public const OPENAI_UNAVAILABLE = 'matching.analysis.openai_unavailable';
    public const OPENAI_REQUEST_FAILED = 'matching.analysis.openai_request_failed';
    public const OPENAI_TIMEOUT = 'matching.analysis.openai_timeout';
    public const OPENAI_CONNECTION_FAILED = 'matching.analysis.openai_connection_failed';
    public const MISSING_GEMINI_KEY = 'matching.analysis.missing_gemini_key';
    public const INVALID_GEMINI_JSON = 'matching.analysis.invalid_gemini_json';
    public const MISSING_GEMINI_OUTPUT = 'matching.analysis.missing_gemini_output';
    public const GEMINI_AUTHENTICATION_FAILED = 'matching.analysis.gemini_authentication_failed';
    public const GEMINI_QUOTA_EXCEEDED = 'matching.analysis.gemini_quota_exceeded';
    public const GEMINI_RATE_LIMITED = 'matching.analysis.gemini_rate_limited';
    public const GEMINI_UNAVAILABLE = 'matching.analysis.gemini_unavailable';
    public const GEMINI_REQUEST_FAILED = 'matching.analysis.gemini_request_failed';
    public const GEMINI_TIMEOUT = 'matching.analysis.gemini_timeout';
    public const GEMINI_CONNECTION_FAILED = 'matching.analysis.gemini_connection_failed';
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
