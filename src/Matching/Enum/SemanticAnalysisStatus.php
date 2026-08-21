<?php

declare(strict_types=1);

namespace App\Matching\Enum;

enum SemanticAnalysisStatus: string
{
    case NOT_REQUESTED = 'NOT_REQUESTED';
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
