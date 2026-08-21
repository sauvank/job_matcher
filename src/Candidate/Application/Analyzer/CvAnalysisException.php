<?php

declare(strict_types=1);

namespace App\Candidate\Application\Analyzer;

final class CvAnalysisException extends \RuntimeException
{
    public function __construct(
        string $messageKey,
        public readonly bool $retryable,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($messageKey, 0, $previous);
    }
}
