<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;

final readonly class SmartJobSearchQueryGenerator
{
    /** @var list<string> Common non-tech / methodology or overly generic terms to exclude from search titles */
    private const EXCLUDED_SKILL_NAMES = [
        'git', 'github', 'gitlab', 'bitbucket', 'agile', 'scrum', 'kanban', 'jira', 'confluence',
        'trello', 'api', 'api rest', 'rest', 'restful', 'graphql', 'soap', 'sql', 'nosql',
        'clean code', 'solid', 'tdd', 'bdd', 'ci/cd', 'ci cd', 'devops', 'anglais', 'english',
        'gestion de projet', 'communication', 'management', 'leadership', 'redaction',
        'veille technologique', 'autonomie', 'rigueur', 'travail en equipe', 'conception',
    ];

    /**
     * Generates optimized job search queries based on the candidate's base title and CV skills.
     *
     * @return list<string>
     */
    public function generate(CandidateProfile $profile): array
    {
        $baseTitle = trim($profile->getTitle() ?? '');
        if ($baseTitle === '') {
            return [];
        }

        $skills = $this->extractRankedSkills($profile);
        $queries = [$baseTitle];

        if ($skills === []) {
            return $queries;
        }

        $topSkills = array_slice($skills, 0, 4);
        $normalizedTitle = mb_strtolower($baseTitle);

        $isFullstack = (bool) preg_match('/(?:full\s*stack|fullstack)/iu', $normalizedTitle);
        $isBackend = (bool) preg_match('/(?:back\s*end|backend)/iu', $normalizedTitle);
        $isFrontend = (bool) preg_match('/(?:front\s*end|frontend)/iu', $normalizedTitle);
        $isLead = (bool) preg_match('/(?:lead|tech\s*lead|lead\s*tech|responsable\s*tech)/iu', $normalizedTitle);
        $isArchitect = (bool) preg_match('/(?:architecte|architect)/iu', $normalizedTitle);
        $isDev = (bool) preg_match('/(?:dev|d[ée]veloppeur|developer|ing[ée]nieur|software\s*engineer)/iu', $normalizedTitle);

        // 1. Combine base title / role with top individual skills
        foreach ($topSkills as $skill) {
            $skillName = $skill['name'];
            if (!$this->titleContainsSkill($normalizedTitle, $skillName)) {
                // e.g. "Développeur Full Stack PHP", "Dev full stack React"
                $queries[] = $baseTitle.' '.$skillName;

                // Also generate concise specialized role e.g. "Développeur Symfony", "Lead Developer Symfony"
                if ($isLead) {
                    $queries[] = 'Lead Developer '.$skillName;
                } elseif ($isArchitect) {
                    $queries[] = 'Architecte '.$skillName;
                } elseif ($isFullstack || $isBackend || $isFrontend || $isDev) {
                    $queries[] = 'Développeur '.$skillName;
                }
            }
        }

        // 2. Pair query for the top 2 complementary technologies (e.g. "Développeur PHP Symfony", "Développeur React Node")
        if (count($topSkills) >= 2) {
            $skill1 = $topSkills[0]['name'];
            $skill2 = $topSkills[1]['name'];
            if (!$this->titleContainsSkill($normalizedTitle, $skill1) || !$this->titleContainsSkill($normalizedTitle, $skill2)) {
                if ($isLead) {
                    $queries[] = 'Lead Developer '.$skill1.' '.$skill2;
                } else {
                    $queries[] = 'Développeur '.$skill1.' '.$skill2;
                }
            }
        }

        return $this->deduplicateAndFormat($queries);
    }

    /**
     * @return list<array{name: string, score: int, category: ?SkillCategory}>
     */
    private function extractRankedSkills(CandidateProfile $profile): array
    {
        $skills = [];
        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            $skill = $candidateSkill->getSkill();
            $name = trim($skill->getName());
            $normalizedName = mb_strtolower($name);

            if (in_array($normalizedName, self::EXCLUDED_SKILL_NAMES, true)) {
                continue;
            }

            // Remove noisy version suffixes: "PHP 8.2" -> "PHP", "Angular 17" -> "Angular"
            $cleanedName = preg_replace('/\s+\d+(?:\.[\dx]+)*(?:\+|\.x)?$/iu', '', $name);
            $cleanedName = is_string($cleanedName) && trim($cleanedName) !== '' ? trim($cleanedName) : $name;
            if (mb_strlen($cleanedName) < 2) {
                continue;
            }

            $score = 0;
            if ($candidateSkill->isCoreSkill()) {
                $score += 100;
            }

            $level = $candidateSkill->getLevel();
            if ($level === SkillLevel::EXPERT) {
                $score += 40;
            } elseif ($level === SkillLevel::ADVANCED) {
                $score += 30;
            } elseif ($level === SkillLevel::INTERMEDIATE) {
                $score += 15;
            }

            $category = $skill->getCategory();
            if ($category === SkillCategory::BACKEND) {
                $score += 50;
            } elseif ($category === SkillCategory::FRONTEND) {
                $score += 45;
            } elseif ($category === SkillCategory::DEVOPS) {
                $score += 25;
            } elseif ($category === SkillCategory::DATABASE) {
                $score += 20;
            }

            $skills[] = [
                'name' => $cleanedName,
                'score' => $score,
                'category' => $category,
            ];
        }

        // Sort by score descending
        usort($skills, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        // Unique by cleaned name
        $unique = [];
        $seen = [];
        foreach ($skills as $item) {
            $lower = mb_strtolower($item['name']);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    private function titleContainsSkill(string $normalizedTitle, string $skillName): bool
    {
        $normSkill = mb_strtolower($skillName);

        return str_contains(' '.$normalizedTitle.' ', ' '.$normSkill.' ');
    }

    /**
     * @param list<string> $queries
     *
     * @return list<string>
     */
    private function deduplicateAndFormat(array $queries): array
    {
        $result = [];
        $seen = [];
        foreach ($queries as $query) {
            $trimmed = trim((string) preg_replace('/\s+/u', ' ', $query));
            if ($trimmed === '') {
                continue;
            }
            $lower = mb_strtolower($trimmed);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $result[] = $trimmed;
            }
        }

        return $result;
    }
}
