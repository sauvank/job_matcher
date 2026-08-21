<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum RemotePolicy: string
{
    case ON_SITE = 'ON_SITE';
    case HYBRID = 'HYBRID';
    case REMOTE = 'REMOTE';
    case FLEXIBLE = 'FLEXIBLE';
    case UNKNOWN = 'UNKNOWN';
}
