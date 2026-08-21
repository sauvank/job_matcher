<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Application\DTO;

use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Translation\CandidateMessage;
use PHPUnit\Framework\TestCase;

final class CvAnalysisResultTest extends TestCase
{
    public function testStructuredAnalysisCanBeHydratedAndSerialized(): void
    {
        $payload = [
            'suggestedTitle' => 'Développeur Symfony',
            'location' => 'Paris',
            'yearsOfExperience' => 6,
            'skills' => [[
                'name' => 'Symfony',
                'category' => 'BACKEND',
                'level' => 'ADVANCED',
                'yearsOfExperience' => 5,
                'isCoreSkill' => true,
                'confidence' => 0.96,
            ]],
            'summary' => 'Profil backend confirmé.',
            'warnings' => [],
        ];

        $analysis = CvAnalysisResult::fromArray($payload);

        self::assertSame('Développeur Symfony', $analysis->suggestedTitle);
        self::assertSame('Symfony', $analysis->skills[0]->name);
        self::assertSame($payload, $analysis->toArray());
    }

    public function testInvalidSkillMetadataUsesATranslatableMessageKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(CandidateMessage::INVALID_SKILL_METADATA);

        CvAnalysisResult::fromArray([
            'suggestedTitle' => null,
            'location' => null,
            'yearsOfExperience' => null,
            'skills' => [[
                'name' => 'PHP',
                'category' => 'BACKEND',
                'level' => null,
                'yearsOfExperience' => null,
                'isCoreSkill' => 'yes',
                'confidence' => 0.9,
            ]],
            'summary' => '',
            'warnings' => [],
        ]);
    }
}
