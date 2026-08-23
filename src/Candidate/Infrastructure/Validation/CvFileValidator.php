<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Validation;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Translation\CandidateMessage;

final readonly class CvFileValidator
{
    private const PDF_MIME_TYPE = 'application/pdf';
    private const DOCX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];

    public function __construct(
        private int $maxFileBytes,
        private int $maxDocxEntries,
        private int $maxDocxEntryBytes,
        private int $maxDocxUncompressedBytes,
    ) {
    }

    public function validate(string $path, string $declaredMimeType): void
    {
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > $this->maxFileBytes) {
            throw new CvExtractionException(CandidateMessage::FILE_UNREADABLE);
        }

        if ($declaredMimeType === self::PDF_MIME_TYPE) {
            $this->validatePdf($path, $size);

            return;
        }

        if (in_array($declaredMimeType, self::DOCX_MIME_TYPES, true)) {
            $this->validateDocx($path);

            return;
        }

        throw new CvExtractionException(CandidateMessage::FORMAT_NOT_SUPPORTED);
    }

    private function validatePdf(string $path, int $size): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new CvExtractionException(CandidateMessage::FILE_UNREADABLE);
        }

        try {
            $header = fread($handle, 8);
            if (!is_string($header) || preg_match('/^%PDF-(?:1\.[0-7]|2\.0)/', $header) !== 1) {
                throw new CvExtractionException(CandidateMessage::INVALID_FILE_SIGNATURE);
            }

            $tailLength = max(1, min($size, 4096));
            if (fseek($handle, -$tailLength, SEEK_END) !== 0) {
                throw new CvExtractionException(CandidateMessage::FILE_UNREADABLE);
            }

            $tail = fread($handle, $tailLength);
            if (!is_string($tail) || preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/s', $tail, $matches) !== 1) {
                throw new CvExtractionException(CandidateMessage::INVALID_PDF_STRUCTURE);
            }

            $xrefOffset = (int) $matches[1];
            if ($xrefOffset <= 0 || $xrefOffset >= $size || fseek($handle, $xrefOffset) !== 0) {
                throw new CvExtractionException(CandidateMessage::INVALID_PDF_STRUCTURE);
            }

            $xrefLength = $size - $xrefOffset;
            if ($xrefLength <= 0) {
                throw new CvExtractionException(CandidateMessage::INVALID_PDF_STRUCTURE);
            }

            $xref = fread($handle, min(512, $xrefLength));
            if (!is_string($xref) || !$this->isValidPdfCrossReference($xref)) {
                throw new CvExtractionException(CandidateMessage::INVALID_PDF_STRUCTURE);
            }
        } finally {
            fclose($handle);
        }
    }

    private function isValidPdfCrossReference(string $content): bool
    {
        return str_starts_with($content, 'xref')
            || preg_match('/^\d+\s+\d+\s+obj\b.*?\/Type\s*\/XRef\b/s', $content) === 1;
    }

    private function validateDocx(string $path): void
    {
        $signature = file_get_contents($path, false, null, 0, 4);
        if ($signature !== "PK\x03\x04") {
            throw new CvExtractionException(CandidateMessage::INVALID_FILE_SIGNATURE);
        }

        $archive = new \ZipArchive();
        if ($archive->open($path, \ZipArchive::CHECKCONS) !== true) {
            throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
        }

        try {
            if ($archive->numFiles <= 0 || $archive->numFiles > $this->maxDocxEntries) {
                throw new CvExtractionException(CandidateMessage::DOCX_LIMIT_EXCEEDED);
            }

            $uncompressedBytes = 0;
            $entryNames = [];
            for ($index = 0; $index < $archive->numFiles; ++$index) {
                $entry = $archive->statIndex($index, \ZipArchive::FL_UNCHANGED);
                if ($entry === false) {
                    throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
                }

                $this->assertSafeEntryName($entry['name'], $entryNames);
                if ($entry['encryption_method'] !== \ZipArchive::EM_NONE) {
                    throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
                }

                if ($entry['size'] > $this->maxDocxEntryBytes) {
                    throw new CvExtractionException(CandidateMessage::DOCX_LIMIT_EXCEEDED);
                }

                $uncompressedBytes += $entry['size'];
                if ($uncompressedBytes > $this->maxDocxUncompressedBytes) {
                    throw new CvExtractionException(CandidateMessage::DOCX_LIMIT_EXCEEDED);
                }
            }

            foreach (['[Content_Types].xml', '_rels/.rels', 'word/document.xml'] as $requiredEntry) {
                if (!isset($entryNames[$requiredEntry])) {
                    throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
                }
            }

            $contentTypes = $archive->getFromName('[Content_Types].xml', 1048576, \ZipArchive::FL_UNCHANGED);
            if (!is_string($contentTypes) || !str_contains($contentTypes, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')) {
                throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
            }

            $documentXml = $archive->getFromName('word/document.xml', $this->maxDocxEntryBytes, \ZipArchive::FL_UNCHANGED);
            if (!is_string($documentXml) || preg_match('/<w:document\b/', $documentXml) !== 1) {
                throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
            }
        } finally {
            $archive->close();
        }
    }

    /** @param array<string, true> $entryNames */
    private function assertSafeEntryName(string $name, array &$entryNames): void
    {
        $normalizedName = str_replace('\\', '/', $name);
        if ($normalizedName === ''
            || str_starts_with($normalizedName, '/')
            || preg_match('/^[A-Za-z]:\//', $normalizedName) === 1
            || in_array('..', explode('/', $normalizedName), true)
            || isset($entryNames[$normalizedName])) {
            throw new CvExtractionException(CandidateMessage::INVALID_DOCX_STRUCTURE);
        }

        $entryNames[$normalizedName] = true;
    }
}
