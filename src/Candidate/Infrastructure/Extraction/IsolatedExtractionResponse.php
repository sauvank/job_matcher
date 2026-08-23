<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Extraction;

final readonly class IsolatedExtractionResponse
{
    private function __construct(
        public bool $successful,
        public ?string $text,
        public ?string $error,
    ) {
    }

    public static function success(string $text): self
    {
        return new self(true, $text, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
