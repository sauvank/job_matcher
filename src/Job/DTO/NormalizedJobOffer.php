<?php

declare(strict_types=1);

namespace App\Job\DTO;

final readonly class NormalizedJobOffer
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public string $externalId,
        public string $url,
        public string $title,
        public ?string $company,
        public ?string $location,
        public ?string $contractType,
        public ?int $minimumSalary,
        public ?int $maximumSalary,
        public ?string $remotePolicy,
        public ?int $yearsOfExperience,
        public ?string $description,
        public ?\DateTimeImmutable $publishedAt,
        public ?\DateTimeImmutable $validThrough,
        public array $rawPayload,
    ) {
    }

    public function contentHash(): string
    {
        return hash('sha256', json_encode([
            $this->title,
            $this->company,
            $this->location,
            $this->contractType,
            $this->minimumSalary,
            $this->maximumSalary,
            $this->remotePolicy,
            $this->yearsOfExperience,
            $this->description,
            $this->publishedAt?->format(DATE_ATOM),
            $this->validThrough?->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
    }
}
