<?php

declare(strict_types=1);

namespace App\Job\Enum;

enum JobOfferAvailability
{
    case AVAILABLE;
    case EXPIRED;
    case UNKNOWN;
}
