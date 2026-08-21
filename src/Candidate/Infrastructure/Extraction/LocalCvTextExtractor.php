<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Extraction;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Application\Extraction\CvTextExtractorInterface;
use App\Candidate\Application\Storage\CvStorageInterface;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Translation\CandidateMessage;
use Symfony\Component\Process\Process;

final readonly class LocalCvTextExtractor implements CvTextExtractorInterface
{
    public function __construct(private CvStorageInterface $storage)
    {
    }

    public function extract(CvDocument $document): string
    {
        $path = $this->storage->absolutePath($document->getStoredFilename());
        if (!is_file($path)) {
            throw new CvExtractionException(CandidateMessage::FILE_NOT_FOUND);
        }

        $text = match ($document->getMimeType()) {
            'application/pdf' => $this->extractPdf($path),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' => $this->extractDocx($path),
            default => throw new CvExtractionException(CandidateMessage::FORMAT_NOT_SUPPORTED),
        };

        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? $text;
        $text = trim($text);

        if (mb_strlen($text) < 50) {
            throw new CvExtractionException(CandidateMessage::TEXT_TOO_SHORT);
        }

        return $text;
    }

    private function extractPdf(string $path): string
    {
        $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CvExtractionException(CandidateMessage::PDF_EXTRACTION_FAILED);
        }

        return $process->getOutput();
    }

    private function extractDocx(string $path): string
    {
        $process = new Process(['unzip', '-p', $path, 'word/document.xml']);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CvExtractionException(CandidateMessage::DOCX_EXTRACTION_FAILED);
        }

        $xml = preg_replace('/<\/w:p>/u', "\n", $process->getOutput()) ?? $process->getOutput();
        $xml = preg_replace('/<w:tab\s*\/>/u', "\t", $xml) ?? $xml;

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
