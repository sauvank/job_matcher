<?php

declare(strict_types=1);

namespace App\Job\Enum;

enum JobSourceSyncStatus: string
{
    case IDLE = 'IDLE';
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case SUCCEEDED = 'SUCCEEDED';
    case FAILED = 'FAILED';
}
