<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Validation;

use App\Candidate\Application\Extraction\CvExtractionException;
use App\Candidate\Infrastructure\Validation\CvFileValidator;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CvFileValidatorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/job-matcher-cv-validator-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($this->temporaryDirectory);
    }

    public function testItAcceptsAPdfWithAConsistentHeaderTrailerAndCrossReference(): void
    {
        $path = $this->path('valid.pdf');
        $prefix = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n";
        $xrefOffset = strlen($prefix);
        file_put_contents($path, $prefix."xref\n0 1\n0000000000 65535 f \ntrailer\n<< /Size 1 >>\nstartxref\n".$xrefOffset."\n%%EOF\n");

        $this->validator()->validate($path, 'application/pdf');

        self::assertFileExists($path);
    }

    #[DataProvider('invalidPdfProvider')]
    public function testItRejectsAnInvalidPdf(string $contents, string $expectedMessage): void
    {
        $path = $this->path('invalid.pdf');
        file_put_contents($path, $contents);

        $this->expectException(CvExtractionException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->validator()->validate($path, 'application/pdf');
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPdfProvider(): iterable
    {
        yield 'false signature' => ['not a pdf', CandidateMessage::INVALID_FILE_SIGNATURE];
        yield 'missing cross reference' => ["%PDF-1.4\n%%EOF\n", CandidateMessage::INVALID_PDF_STRUCTURE];
        yield 'out of bounds cross reference' => ["%PDF-1.4\nstartxref\n9999\n%%EOF\n", CandidateMessage::INVALID_PDF_STRUCTURE];
    }

    public function testItAcceptsAValidDocxPackage(): void
    {
        $path = $this->path('valid.docx');
        $this->createDocx($path);

        $this->validator()->validate($path, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        self::assertFileExists($path);
    }

    public function testItRejectsAZipThatIsNotADocx(): void
    {
        $path = $this->path('fake.docx');
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($path, \ZipArchive::CREATE));
        $archive->addFromString('payload.txt', 'not a document');
        $archive->close();

        $this->expectException(CvExtractionException::class);
        $this->expectExceptionMessage(CandidateMessage::INVALID_DOCX_STRUCTURE);

        $this->validator()->validate($path, 'application/zip');
    }

    public function testItRejectsUnsafeArchivePaths(): void
    {
        $path = $this->path('unsafe.docx');
        $this->createDocx($path, ['../payload.txt' => 'unsafe']);

        $this->expectException(CvExtractionException::class);
        $this->expectExceptionMessage(CandidateMessage::INVALID_DOCX_STRUCTURE);

        $this->validator()->validate($path, 'application/zip');
    }

    public function testItRejectsADocxEntryThatExceedsThePerEntryLimit(): void
    {
        $path = $this->path('large.docx');
        $this->createDocx($path, ['word/large.xml' => str_repeat('a', 600)]);

        $this->expectException(CvExtractionException::class);
        $this->expectExceptionMessage(CandidateMessage::DOCX_LIMIT_EXCEEDED);

        $this->validator(maxEntryBytes: 500, maxUncompressedBytes: 1000)->validate($path, 'application/zip');
    }

    public function testItRejectsADocxThatExceedsTheTotalDecompressedLimit(): void
    {
        $path = $this->path('bomb.docx');
        $this->createDocx($path, [
            'word/large-1.xml' => str_repeat('a', 300),
            'word/large-2.xml' => str_repeat('b', 300),
        ]);

        $this->expectException(CvExtractionException::class);
        $this->expectExceptionMessage(CandidateMessage::DOCX_LIMIT_EXCEEDED);

        $this->validator(maxEntryBytes: 1000, maxUncompressedBytes: 700)->validate($path, 'application/zip');
    }

    /** @param array<string, string> $extraEntries */
    private function createDocx(string $path, array $extraEntries = []): void
    {
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($path, \ZipArchive::CREATE));
        $archive->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>
XML);
        $archive->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $archive->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>CV de test</w:t></w:r></w:p></w:body></w:document>');
        foreach ($extraEntries as $name => $contents) {
            $archive->addFromString($name, $contents);
        }
        $archive->close();
    }

    private function validator(int $maxEntryBytes = 1024, int $maxUncompressedBytes = 4096): CvFileValidator
    {
        return new CvFileValidator(
            maxFileBytes: 1048576,
            maxDocxEntries: 20,
            maxDocxEntryBytes: $maxEntryBytes,
            maxDocxUncompressedBytes: $maxUncompressedBytes,
        );
    }

    private function path(string $filename): string
    {
        return $this->temporaryDirectory.'/'.$filename;
    }
}
