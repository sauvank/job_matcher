<?php

declare(strict_types=1);

namespace App\Matching\DTO;

use App\Matching\Enum\JobApplicationStatus;

final class JobApplicationStatusData
{
    public JobApplicationStatus $status = JobApplicationStatus::UNPROCESSED;
    public ?string $reason = null;
}
