<?php

declare(strict_types=1);

namespace App\Job\Service;

use App\Job\Entity\JobOffer;

final readonly class TechnicalRequirementExtractor
{
    /** @var array<string, list<string>> */
    private const DETECTABLE_REQUIREMENTS = [
        'Symfony' => ['symfony'],
        'Angular' => ['angular'],
        'React' => ['react'],
        'Webhooks' => ['webhook', 'webhooks'],
        'CI/CD' => ['ci/cd', 'ci cd'],
    ];

    /** @return list<string> */
    public function extract(JobOffer $offer): array
    {
        $payload = $offer->getRawPayload();
        $skills = [];
        $structuredSkills = $payload['skills'] ?? [];
        if (is_array($structuredSkills)) {
            foreach ($structuredSkills as $skill) {
                if (is_string($skill) && trim($skill) !== '' && $this->normalize($skill) !== 'clean') {
                    $skills[$this->normalize($skill)] = trim($skill);
                }
            }
        }

        $description = is_string($payload['description'] ?? null) ? $payload['description'] : '';
        foreach ($this->extractTechnicalEnvironment($description) as $skill) {
            $skills[$this->normalize($skill)] = $skill;
        }

        $qualifications = is_string($payload['qualifications'] ?? null) ? $payload['qualifications'] : '';
        $requirementsText = $this->normalize($offer->getTitle().' '.$qualifications.' '.$description);
        foreach (self::DETECTABLE_REQUIREMENTS as $label => $signals) {
            if (array_any($signals, fn (string $signal): bool => $this->contains($requirementsText, $signal))) {
                $skills[$this->normalize($label)] = $label;
            }
        }

        return array_values($skills);
    }

    /** @return list<string> */
    private function extractTechnicalEnvironment(string $html): array
    {
        if (preg_match('~(?:Environnement|Stack)\s+technique\s*:(.*?)(?:</p>|<h2)~isu', $html, $matches) !== 1) {
            return [];
        }

        $section = preg_replace('~<br\s*/?>~iu', "\n", $matches[1]);
        if (!is_string($section)) {
            return [];
        }

        $skills = [];
        foreach (preg_split('/\R+/u', html_entity_decode(strip_tags($section), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $line) {
            $value = preg_replace('/^[^:]+:\s*/u', '', trim($line));
            $value = is_string($value) ? preg_replace('/\s*\([^)]*\)\s*$/u', '', $value) : null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            foreach (preg_split('/\s*,\s*|\s+\/\s+/u', $value) ?: [] as $skill) {
                $skill = preg_replace('/\s+\d+(?:\.[\dx]+)*(?:\+|\.x)?$/iu', '', trim($skill));
                if (is_string($skill) && mb_strlen($skill) >= 2) {
                    $skills[] = $skill;
                }
            }
        }

        return $skills;
    }

    private function contains(string $haystack, string $needle): bool
    {
        $needle = $this->normalize($needle);

        return $needle !== '' && str_contains(' '.$haystack.' ', ' '.$needle.' ');
    }

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($value));

        return is_string($normalized) ? trim($normalized) : '';
    }
}
