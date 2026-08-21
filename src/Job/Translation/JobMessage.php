<?php

declare(strict_types=1);

namespace App\Job\Translation;

final class JobMessage
{
    public const SYNC_DISPATCHED = 'job.source.sync_dispatched';
    public const INVALID_URL = 'job.source.invalid_url';
    public const UNSUPPORTED_PROVIDER = 'job.source.unsupported_provider';
    public const SEARCH_CRITERIA_REQUIRED = 'job.source.search_criteria_required';
    public const SEARCH_TITLE_REQUIRED = 'job.source.search_title_required';
    public const SEARCH_ADDED = 'job.source.search_added';
    public const SOURCE_NOT_FOUND = 'job.source.not_found';
    public const SYNC_ALREADY_RUNNING = 'job.source.sync_already_running';
    public const SYNC_FAILED = 'job.source.sync_failed';
    public const INVALID_RESPONSE = 'job.provider.invalid_response';
    public const JOB_POSTING_NOT_FOUND = 'job.provider.job_posting_not_found';

    private function __construct()
    {
    }
}
