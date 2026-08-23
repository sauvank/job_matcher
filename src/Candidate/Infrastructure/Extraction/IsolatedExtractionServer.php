<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Extraction;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Application\Storage\CvStorageInterface;
use App\Candidate\Translation\CandidateMessage;

final readonly class IsolatedExtractionServer
{
    /** @param positive-int $requestMaxBytes */
    public function __construct(
        private LocalCvTextExtractor $extractor,
        private CvStorageInterface $storage,
        private IsolatedExtractionProtocol $protocol,
        private string $dsn,
        private int $requestMaxBytes,
        private int $connectionTimeoutSeconds,
    ) {
    }

    public function run(): never
    {
        $socketPath = $this->socketPath();
        if (file_exists($socketPath)) {
            @unlink($socketPath);
        }

        $errorCode = 0;
        $errorMessage = '';
        $server = stream_socket_server($this->dsn, $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if ($server === false) {
            throw new \RuntimeException('Unable to start the isolated CV extraction service.');
        }
        chmod($socketPath, 0660);

        while (true) {
            $connection = @stream_socket_accept($server, -1);
            if ($connection === false) {
                continue;
            }

            try {
                stream_set_timeout($connection, $this->connectionTimeoutSeconds);
                $this->write($connection, $this->handle($this->read($connection)));
            } catch (\Throwable) {
                $this->write($connection, $this->protocol->failure(CandidateMessage::EXTRACTION_SERVICE_FAILED));
            } finally {
                fclose($connection);
            }
        }
    }

    public function handle(string $payload): string
    {
        try {
            $request = $this->protocol->parseRequest($payload);
            $text = $this->extractor->extractFile(
                $this->storage->absolutePath($request['filename']),
                $request['mimeType'],
            );

            return $this->protocol->success($text);
        } catch (CvExtractionException $exception) {
            return $this->protocol->failure($exception->getMessage());
        }
    }

    /** @param resource $connection */
    private function read($connection): string
    {
        $payload = fgets($connection, $this->requestMaxBytes + 1);
        if (!is_string($payload) || !str_ends_with($payload, "\n") || strlen($payload) > $this->requestMaxBytes) {
            throw new \UnexpectedValueException('Invalid extraction request size.');
        }

        return $payload;
    }

    /** @param resource $connection */
    private function write($connection, string $payload): void
    {
        while ($payload !== '') {
            $written = @fwrite($connection, $payload);
            if ($written === false || $written === 0) {
                return;
            }

            $payload = substr($payload, $written);
        }
    }

    private function socketPath(): string
    {
        if (!str_starts_with($this->dsn, 'unix://')) {
            throw new \LogicException('The isolated extractor requires a Unix socket.');
        }

        return substr($this->dsn, 7);
    }
}
