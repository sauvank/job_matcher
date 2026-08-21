<?php

declare(strict_types=1);

namespace App\Candidate\Application\Message;

final readonly class ProcessCvMessage
{
    public function __construct(public int $cvDocumentId)
    {
    }
}
