<?php

declare(strict_types=1);

namespace App\Matching\Message;

final readonly class AnalyzeJobMatchMessage
{
    public function __construct(public int $jobMatchId)
    {
    }
}
