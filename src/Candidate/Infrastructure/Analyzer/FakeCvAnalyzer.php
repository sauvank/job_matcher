<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Analyzer;

use App\Candidate\Application\Analyzer\CvAnalyzerInterface;
use App\Candidate\Application\DTO\AnalyzedSkill;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;

final class FakeCvAnalyzer implements CvAnalyzerInterface
{
    private const SKILLS = [
        'PHP' => SkillCategory::BACKEND,
        'Symfony' => SkillCategory::BACKEND,
        'PostgreSQL' => SkillCategory::DATABASE,
        'MySQL' => SkillCategory::DATABASE,
        'MariaDB' => SkillCategory::DATABASE,
        'Docker' => SkillCategory::DEVOPS,
        'Redis' => SkillCategory::DATABASE,
        'PHPUnit' => SkillCategory::TESTING,
        'React' => SkillCategory::FRONTEND,
        'Vue.js' => SkillCategory::FRONTEND,
    ];

    public function analyze(string $cvText): CvAnalysisResult
    {
        $skills = [];
        foreach (self::SKILLS as $name => $category) {
            if (stripos($cvText, $name) === false) {
                continue;
            }

            $skills[] = new AnalyzedSkill($name, $category, SkillLevel::ADVANCED, null, in_array($name, ['PHP', 'Symfony'], true), 0.9);
        }

        preg_match_all('/\b(\d{1,2})\s*(?:ans?|années?|years?)\b/iu', $cvText, $matches);
        $years = $matches[1] === [] ? null : max(array_map('intval', $matches[1]));
        $title = false !== stripos($cvText, 'Symfony') ? 'Développeur backend PHP/Symfony' : null;

        return new CvAnalysisResult(
            $title,
            null,
            $years,
            $skills,
            'Analyse locale de démonstration. Configurez OpenAI pour obtenir une analyse sémantique complète.',
            ['Ce résultat provient du faux analyseur local.'],
        );
    }

    public function name(): string
    {
        return 'fake';
    }
}
