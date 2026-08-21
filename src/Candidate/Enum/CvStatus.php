<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum CvStatus: string
{
    case UPLOADED = 'UPLOADED';
    case EXTRACTING = 'EXTRACTING';
    case ANALYZING = 'ANALYZING';
    case READY = 'READY';
    case APPLIED = 'APPLIED';
    case FAILED = 'FAILED';
}
