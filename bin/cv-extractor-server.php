<?php

declare(strict_types=1);

use App\Candidate\Infrastructure\Extraction\IsolatedExtractionProtocol;
use App\Candidate\Infrastructure\Extraction\IsolatedExtractionServer;
use App\Candidate\Infrastructure\Extraction\LocalCvTextExtractor;
use App\Candidate\Infrastructure\Storage\LocalPrivateCvStorage;
use App\Candidate\Infrastructure\Validation\CvFileValidator;

require dirname(__DIR__).'/vendor/autoload.php';

$storage = new LocalPrivateCvStorage('/app/var/cv');
$validator = new CvFileValidator(
    maxFileBytes: 10 * 1024 * 1024,
    maxDocxEntries: 1000,
    maxDocxEntryBytes: 10 * 1024 * 1024,
    maxDocxUncompressedBytes: 50 * 1024 * 1024,
);
$extractor = new LocalCvTextExtractor($storage, $validator, 2 * 1024 * 1024);

(new IsolatedExtractionServer(
    $extractor,
    $storage,
    new IsolatedExtractionProtocol(),
    'unix:///run/cv-extractor/extractor.sock',
    1024,
    40,
))->run();
