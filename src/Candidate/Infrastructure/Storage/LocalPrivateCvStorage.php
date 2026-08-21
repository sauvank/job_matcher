<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Storage;

use App\Candidate\Application\DTO\StoredCvFile;
use App\Candidate\Application\Storage\CvStorageInterface;
use App\Candidate\Translation\CandidateMessage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class LocalPrivateCvStorage implements CvStorageInterface
{
    public function __construct(private string $storageDirectory)
    {
    }

    public function store(UploadedFile $file): StoredCvFile
    {
        $mimeType = $file->getMimeType();
        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' => 'docx',
            default => throw new \RuntimeException(CandidateMessage::FILE_TYPE_NOT_ALLOWED),
        };

        $size = $file->getSize();
        $sha256 = hash_file('sha256', $file->getPathname());
        if ($size === false || $sha256 === false) {
            throw new \RuntimeException(CandidateMessage::FILE_UNREADABLE);
        }

        (new Filesystem())->mkdir($this->storageDirectory, 0700);
        $storedFilename = Uuid::v7()->toRfc4122().'.'.$extension;
        $originalFilename = $file->getClientOriginalName();
        $file->move($this->storageDirectory, $storedFilename);

        return new StoredCvFile($originalFilename, $storedFilename, $mimeType, $size, $sha256);
    }

    public function absolutePath(string $storedFilename): string
    {
        return $this->storageDirectory.DIRECTORY_SEPARATOR.basename($storedFilename);
    }

    public function delete(string $storedFilename): void
    {
        (new Filesystem())->remove($this->absolutePath($storedFilename));
    }
}
