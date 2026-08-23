<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Extraction;

use App\Candidate\Infrastructure\Extraction\IsolatedExtractionProtocol;
use App\Candidate\Infrastructure\Extraction\IsolatedExtractionServer;
use App\Candidate\Infrastructure\Extraction\LocalCvTextExtractor;
use App\Candidate\Infrastructure\Storage\LocalPrivateCvStorage;
use App\Candidate\Infrastructure\Validation\CvFileValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class IsolatedExtractionServerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/job-matcher-extractor-'.bin2hex(random_bytes(8));
        (new Filesystem())->mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testItValidatesAndExtractsADocxAcrossTheIsolationProtocol(): void
    {
        $filename = '0198d421-caf2-7000-8000-123456789abc.docx';
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($this->directory.'/'.$filename, \ZipArchive::CREATE));
        $archive->addFromString(
            '[Content_Types].xml',
            '<Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        );
        $archive->addFromString('_rels/.rels', '<Relationships/>');
        $archive->addFromString(
            'word/document.xml',
            '<w:document><w:body><w:p><w:r><w:t>Développeur PHP Symfony avec une solide expérience Docker et API backend.</w:t></w:r></w:p></w:body></w:document>',
        );
        self::assertTrue($archive->close());

        $storage = new LocalPrivateCvStorage($this->directory);
        $validator = new CvFileValidator(10_485_760, 1000, 10_485_760, 52_428_800);
        $extractor = new LocalCvTextExtractor($storage, $validator, 2_097_152);
        $protocol = new IsolatedExtractionProtocol();
        $server = new IsolatedExtractionServer(
            $extractor,
            $storage,
            $protocol,
            'unix://'.$this->directory.'/extractor.sock',
            1024,
            40,
        );

        $response = $protocol->parseResponse($server->handle($protocol->request(
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        )));

        self::assertTrue($response->successful);
        self::assertSame('Développeur PHP Symfony avec une solide expérience Docker et API backend.', $response->text);
    }
}
