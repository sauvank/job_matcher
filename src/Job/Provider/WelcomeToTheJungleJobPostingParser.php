<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Translation\JobMessage;

final class WelcomeToTheJungleJobPostingParser
{
    /** @param array<string, mixed> $hit */
    public function parseOffer(array $hit): NormalizedJobOffer
    {
        $externalId = $this->stringValue($hit, 'reference') ?? $this->stringValue($hit, 'objectID');
        $slug = $this->stringValue($hit, 'slug');
        $title = $this->stringValue($hit, 'name');
        $organization = $hit['organization'] ?? null;
        $organizationSlug = $this->stringValue($organization, 'slug');

        if ($externalId === null || $slug === null || $title === null || $organizationSlug === null) {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $company = $this->stringValue($organization, 'name');
        $description = $this->cleanText($hit['profile'] ?? null);
        $experience = $hit['experience_level_minimum'] ?? null;

        return new NormalizedJobOffer(
            externalId: $externalId,
            url: sprintf('https://www.welcometothejungle.com/fr/companies/%s/jobs/%s', rawurlencode($organizationSlug), rawurlencode($slug)),
            title: $title,
            company: $company,
            location: $this->extractLocations($hit),
            contractType: $this->extractContractType($hit),
            minimumSalary: $this->integerValue($hit['salary_yearly_minimum'] ?? null),
            maximumSalary: $this->integerValue($hit['salary_yearly_maximum'] ?? null),
            remotePolicy: $this->extractRemotePolicy($hit),
            yearsOfExperience: is_numeric($experience) ? (int) ceil((float) $experience) : null,
            description: $description,
            publishedAt: $this->dateValue($hit['published_at'] ?? null),
            validThrough: null,
            rawPayload: $hit,
        );
    }

    /** @param array<string, mixed> $hit */
    private function extractLocations(array $hit): ?string
    {
        $locations = [];
        $offices = $hit['offices'] ?? null;
        if (is_array($offices)) {
            foreach ($offices as $office) {
                $city = $this->stringValue($office, 'city');
                if ($city !== null) {
                    $locations[] = $city;
                }
            }
        }

        if ($locations === []) {
            $city = $this->stringValue($hit['office'] ?? null, 'city');
            if ($city !== null) {
                $locations[] = $city;
            }
        }

        $locations = array_values(array_unique($locations));

        return $locations === [] ? null : implode(', ', $locations);
    }

    /** @param array<string, mixed> $hit */
    private function extractContractType(array $hit): ?string
    {
        $names = $hit['contract_type_names'] ?? null;

        return $this->stringValue($names, 'fr') ?? match ($this->stringValue($hit, 'contract_type')) {
            'FULL_TIME' => 'CDI',
            'TEMPORARY' => 'CDD',
            'INTERNSHIP' => 'Stage',
            'APPRENTICESHIP' => 'Alternance',
            'FREELANCE' => 'Freelance',
            default => null,
        };
    }

    /** @param array<string, mixed> $hit */
    private function extractRemotePolicy(array $hit): ?string
    {
        return match ($this->stringValue($hit, 'remote')) {
            'fulltime', 'partial', 'punctual' => 'REMOTE_AVAILABLE',
            default => null,
        };
    }

    private function cleanText(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\R{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function integerValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function dateValue(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringValue(mixed $value, string $key): ?string
    {
        if (!is_array($value) || !isset($value[$key]) || !is_string($value[$key])) {
            return null;
        }

        $result = trim($value[$key]);

        return $result === '' ? null : $result;
    }
}
