<?php

declare(strict_types=1);

namespace App\Tests\Unit\Candidate\Infrastructure\Analyzer;

use App\Candidate\Infrastructure\Analyzer\FakeCvAnalyzer;
use PHPUnit\Framework\TestCase;

final class FakeCvAnalyzerTest extends TestCase
{
    public function testItDetectsKnownSkillsWithoutCallingAnApi(): void
    {
        $analysis = (new FakeCvAnalyzer())->analyze('Développeur PHP et Symfony depuis 6 ans. Docker, PostgreSQL et PHPUnit.');

        self::assertSame('Développeur backend PHP/Symfony', $analysis->suggestedTitle);
        self::assertSame(6, $analysis->yearsOfExperience);
        self::assertSame(['PHP', 'Symfony', 'PostgreSQL', 'Docker', 'PHPUnit'], array_map(static fn ($skill): string => $skill->name, $analysis->skills));
    }
}
