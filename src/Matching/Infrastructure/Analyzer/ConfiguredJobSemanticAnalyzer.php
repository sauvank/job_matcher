<?php

declare(strict_types=1);

namespace App\Matching\Infrastructure\Analyzer;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\Application\Analyzer\JobSemanticAnalyzerInterface;
use App\Matching\Application\Analyzer\SemanticAnalysisException;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Translation\MatchingMessage;

final readonly class ConfiguredJobSemanticAnalyzer implements JobSemanticAnalyzerInterface
{
    public function __construct(
        private FakeJobSemanticAnalyzer $fakeAnalyzer,
        private OpenAiJobSemanticAnalyzer $openAiAnalyzer,
        private GeminiJobSemanticAnalyzer $geminiAnalyzer,
        private string $mode,
    ) {
    }

    public function analyze(CandidateProfile $profile, JobOffer $offer): SemanticJobAnalysis
    {
        return $this->selectedAnalyzer()->analyze($profile, $offer);
    }

    public function name(): string
    {
        return $this->selectedAnalyzer()->name();
    }

    private function selectedAnalyzer(): JobSemanticAnalyzerInterface
    {
        return match ($this->mode) {
            'fake' => $this->fakeAnalyzer,
            'openai' => $this->openAiAnalyzer,
            'gemini' => $this->geminiAnalyzer,
            default => throw new SemanticAnalysisException(MatchingMessage::UNKNOWN_SEMANTIC_ANALYZER, false),
        };
    }
}
