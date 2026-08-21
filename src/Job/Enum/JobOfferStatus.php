<?php

declare(strict_types=1);

namespace App\Job\Enum;

enum JobOfferStatus: string
{
    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case UNKNOWN = 'UNKNOWN';
}
