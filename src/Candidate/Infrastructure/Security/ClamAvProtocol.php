<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Security;

final readonly class ClamAvProtocol
{
    public function command(): string
    {
        return "zINSTREAM\0";
    }

    public function frame(string $chunk): string
    {
        return pack('N', strlen($chunk)).$chunk;
    }

    public function end(): string
    {
        return pack('N', 0);
    }

    public function parse(string $response): ClamAvScanResult
    {
        $response = trim($response, "\0\r\n ");

        if (preg_match('/:\s+OK$/', $response) === 1) {
            return ClamAvScanResult::CLEAN;
        }

        if (preg_match('/:\s+.+\s+FOUND$/', $response) === 1) {
            return ClamAvScanResult::INFECTED;
        }

        return ClamAvScanResult::ERROR;
    }
}
