<?php

declare(strict_types=1);

namespace App\Candidate\Application\Analyzer;

use App\Candidate\Application\DTO\CvAnalysisResult;

interface CvAnalyzerInterface
{
    public function analyze(string $cvText): CvAnalysisResult;

    public function name(): string;
}
