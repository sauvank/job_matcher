<?php

declare(strict_types=1);

namespace App\Job\Message;

final readonly class ImportJobSourceMessage
{
    public function __construct(public int $jobSourceId)
    {
    }
}
