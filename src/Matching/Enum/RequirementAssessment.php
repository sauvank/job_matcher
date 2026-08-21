<?php

declare(strict_types=1);

namespace App\Matching\Enum;

enum RequirementAssessment: string
{
    case MATCH = 'MATCH';
    case PARTIAL = 'PARTIAL';
    case GAP = 'GAP';
    case UNKNOWN = 'UNKNOWN';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
}
