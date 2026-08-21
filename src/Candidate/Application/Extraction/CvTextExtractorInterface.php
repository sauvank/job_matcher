<?php

declare(strict_types=1);

namespace App\Candidate\Application\Extraction;

use App\Candidate\Entity\CvDocument;

interface CvTextExtractorInterface
{
    public function extract(CvDocument $document): string;
}
