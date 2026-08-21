<?php

declare(strict_types=1);

namespace App\Candidate\Application\Storage;

use App\Candidate\Application\DTO\StoredCvFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface CvStorageInterface
{
    public function store(UploadedFile $file): StoredCvFile;

    public function absolutePath(string $storedFilename): string;

    public function delete(string $storedFilename): void;
}
