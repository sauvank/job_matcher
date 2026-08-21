<?php

declare(strict_types=1);

namespace App\Matching\DTO;

final readonly class MatchReason
{
    /** @param array<string, int|string> $parameters */
    public function __construct(public string $key, public array $parameters = [])
    {
    }

    /** @return array{key: string, parameters: array<string, int|string>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'parameters' => $this->parameters];
    }
}
