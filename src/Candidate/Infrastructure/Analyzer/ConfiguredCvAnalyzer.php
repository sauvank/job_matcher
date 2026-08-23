<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalysisException;
use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Translation\CandidateMessage;

final readonly class ConfiguredCvAnalyzer implements CvAnalyzerInterface
{
    public function __construct(
        private string $mode,
        private FakeCvAnalyzer $fakeAnalyzer,
        private OpenAiCvAnalyzer $openAiAnalyzer,
        private GeminiCvAnalyzer $geminiAnalyzer,
    ) {
    }

    public function analyze(string $cvText): CvAnalysisResult
    {
        return $this->selectedAnalyzer()->analyze($cvText);
    }

    public function name(): string
    {
        return $this->selectedAnalyzer()->name();
    }

    private function selectedAnalyzer(): CvAnalyzerInterface
    {
        return match ($this->mode) {
            'fake' => $this->fakeAnalyzer,
            'openai' => $this->openAiAnalyzer,
            'gemini' => $this->geminiAnalyzer,
            default => throw new CvAnalysisException(CandidateMessage::UNKNOWN_ANALYZER, false),
        };
    }
}
