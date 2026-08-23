<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Security;

enum ClamAvScanResult
{
    case CLEAN;
    case INFECTED;
    case ERROR;
}
