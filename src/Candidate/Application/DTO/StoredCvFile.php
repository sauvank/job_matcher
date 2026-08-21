<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

final readonly class StoredCvFile
{
    public function __construct(
        public string $originalFilename,
        public string $storedFilename,
        public string $mimeType,
        public int $size,
        public string $sha256,
    ) {
    }
}
