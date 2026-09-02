<?php

declare(strict_types=1);

namespace App\Matching\DTO;

final readonly class JobApplicationAssistantResult
{
    /**
     * @param list<array{question: string, context: string, suggestedAnswer: string}> $interviewQuestions
     */
    public function __construct(
        public string $pitch,
        public string $coverLetter,
        public string $followUpMessage,
        public array $interviewQuestions = [],
    ) {
    }
}
