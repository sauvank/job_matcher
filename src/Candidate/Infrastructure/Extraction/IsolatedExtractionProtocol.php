<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Extraction;

use App\Candidate\Translation\CandidateMessage;

final readonly class IsolatedExtractionProtocol
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];
    private const ALLOWED_ERRORS = [
        CandidateMessage::FILE_NOT_FOUND,
        CandidateMessage::FILE_UNREADABLE,
        CandidateMessage::FORMAT_NOT_SUPPORTED,
        CandidateMessage::TEXT_TOO_SHORT,
        CandidateMessage::TEXT_TOO_LARGE,
        CandidateMessage::PDF_EXTRACTION_FAILED,
        CandidateMessage::DOCX_EXTRACTION_FAILED,
        CandidateMessage::INVALID_FILE_SIGNATURE,
        CandidateMessage::INVALID_PDF_STRUCTURE,
        CandidateMessage::INVALID_DOCX_STRUCTURE,
        CandidateMessage::DOCX_LIMIT_EXCEEDED,
        CandidateMessage::EXTRACTION_SERVICE_FAILED,
    ];

    public function request(string $storedFilename, string $mimeType): string
    {
        return $this->encode([
            'filename' => basename($storedFilename),
            'mimeType' => $mimeType,
        ]);
    }

    /** @return array{filename: string, mimeType: string} */
    public function parseRequest(string $payload): array
    {
        $data = $this->decode($payload);
        $filename = $data['filename'] ?? null;
        $mimeType = $data['mimeType'] ?? null;

        if (!is_string($filename)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(?:pdf|docx)$/D', $filename) !== 1
            || !is_string($mimeType)
            || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \UnexpectedValueException('Invalid extraction request.');
        }

        return ['filename' => $filename, 'mimeType' => $mimeType];
    }

    public function success(string $text): string
    {
        return $this->encode(['success' => true, 'text' => $text]);
    }

    public function failure(string $error): string
    {
        return $this->encode(['success' => false, 'error' => $error]);
    }

    public function parseResponse(string $payload): IsolatedExtractionResponse
    {
        $data = $this->decode($payload);
        if (($data['success'] ?? null) === true && is_string($data['text'] ?? null)) {
            return IsolatedExtractionResponse::success($data['text']);
        }
        if (($data['success'] ?? null) === false
            && is_string($data['error'] ?? null)
            && in_array($data['error'], self::ALLOWED_ERRORS, true)) {
            return IsolatedExtractionResponse::failure($data['error']);
        }

        throw new \UnexpectedValueException('Invalid extraction response.');
    }

    /** @param array<string, bool|string> $data */
    private function encode(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
    }

    /** @return array<string, mixed> */
    private function decode(string $payload): array
    {
        $data = json_decode(trim($payload), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \UnexpectedValueException('Invalid extraction payload.');
        }

        return $data;
    }
}
