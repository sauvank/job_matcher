<?php

declare(strict_types=1);

namespace App\Candidate\Application\Extraction;

final class CvExtractionException extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
