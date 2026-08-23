<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Extraction;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Application\Extraction\CvTextExtractorInterface;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Translation\CandidateMessage;

final readonly class IsolatedCvTextExtractor implements CvTextExtractorInterface
{
    public function __construct(
        private IsolatedExtractionProtocol $protocol,
        private string $dsn,
        private float $timeoutSeconds,
        private int $maxResponseBytes,
    ) {
    }

    public function extract(CvDocument $document): string
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = @stream_socket_client($this->dsn, $errorCode, $errorMessage, $this->timeoutSeconds, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
        }

        try {
            stream_set_timeout($socket, (int) ceil($this->timeoutSeconds));
            $this->write($socket, $this->protocol->request($document->getStoredFilename(), $document->getMimeType()));
            $response = $this->read($socket);
        } catch (CvExtractionException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_FAILED, true);
        } finally {
            fclose($socket);
        }

        if (!$response->successful) {
            throw new CvExtractionException($response->error ?? CandidateMessage::EXTRACTION_SERVICE_FAILED);
        }

        return $response->text ?? throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_FAILED, true);
    }

    /** @param resource $socket */
    private function write($socket, string $payload): void
    {
        while ($payload !== '') {
            $written = fwrite($socket, $payload);
            if ($written === false || $written === 0) {
                throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
            }

            $payload = substr($payload, $written);
        }
    }

    /** @param resource $socket */
    private function read($socket): IsolatedExtractionResponse
    {
        $payload = '';
        while (!feof($socket)) {
            $chunk = fread($socket, 8192);
            if ($chunk === false) {
                throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
            }
            if ($chunk === '') {
                $metadata = stream_get_meta_data($socket);
                if ($metadata['timed_out'] || !feof($socket)) {
                    throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
                }

                break;
            }

            $payload .= $chunk;
            if (strlen($payload) > $this->maxResponseBytes) {
                throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_FAILED, true);
            }
        }

        $metadata = stream_get_meta_data($socket);
        if ($metadata['timed_out'] || $payload === '') {
            throw new CvExtractionException(CandidateMessage::EXTRACTION_SERVICE_UNAVAILABLE, true);
        }

        return $this->protocol->parseResponse($payload);
    }
}
