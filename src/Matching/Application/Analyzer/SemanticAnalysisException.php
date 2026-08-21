<?php

declare(strict_types=1);

namespace App\Matching\Application\Analyzer;

final class SemanticAnalysisException extends \RuntimeException
{
    public function __construct(string $messageKey, public readonly bool $retryable, ?\Throwable $previous = null)
    {
        parent::__construct($messageKey, 0, $previous);
    }
}
