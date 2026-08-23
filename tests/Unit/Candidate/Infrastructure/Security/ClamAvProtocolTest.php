<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Security;

use App\Candidate\Infrastructure\Security\ClamAvProtocol;
use App\Candidate\Infrastructure\Security\ClamAvScanResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClamAvProtocolTest extends TestCase
{
    public function testItBuildsTheInstreamProtocolFrames(): void
    {
        $protocol = new ClamAvProtocol();

        self::assertSame("zINSTREAM\0", $protocol->command());
        self::assertSame(pack('N', 3).'PDF', $protocol->frame('PDF'));
        self::assertSame(pack('N', 0), $protocol->end());
    }

    #[DataProvider('responses')]
    public function testItParsesScannerResponses(string $response, ClamAvScanResult $expected): void
    {
        self::assertSame($expected, (new ClamAvProtocol())->parse($response));
    }

    /** @return iterable<string, array{string, ClamAvScanResult}> */
    public static function responses(): iterable
    {
        yield 'clean' => ["stream: OK\0", ClamAvScanResult::CLEAN];
        yield 'infected' => ["stream: Win.Test.EICAR_HDB-1 FOUND\0", ClamAvScanResult::INFECTED];
        yield 'scanner error' => ["stream: Size limit exceeded. ERROR\0", ClamAvScanResult::ERROR];
        yield 'invalid response' => ['', ClamAvScanResult::ERROR];
    }
}
