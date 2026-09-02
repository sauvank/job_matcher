<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Translation\JobMessage;

final class FreeWorkJobPostingParser
{
    /** @param array<string, mixed> $hit */
    public function parseOffer(array $hit): NormalizedJobOffer
    {
        $id = $hit['id'] ?? null;
        $slug = $this->stringValue($hit, 'slug');
        $title = $this->stringValue($hit, 'title');

        if ($id === null || $slug === null || $title === null) {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $companyData = $hit['company'] ?? null;
        $company = is_array($companyData) ? $this->stringValue($companyData, 'name') : null;
        $location = $this->extractLocation($hit);
        $contractType = $this->extractContractType($hit);
        $description = $this->cleanText($this->stringValue($hit, 'description'));
        $experience = $this->extractExperience($hit);

        [$minSalary, $maxSalary] = $this->extractSalary($hit);
        $remotePolicy = $this->extractRemotePolicy($hit);

        return new NormalizedJobOffer(
            externalId: (string) $id,
            url: sprintf('https://www.free-work.com/fr/tech-it/jobs/job-posting/%s', rawurlencode($slug)),
            title: $title,
            company: $company,
            location: $location,
            contractType: $contractType,
            minimumSalary: $minSalary,
            maximumSalary: $maxSalary,
            remotePolicy: $remotePolicy,
            yearsOfExperience: $experience,
            description: $description,
            publishedAt: $this->dateValue($hit['publishedAt'] ?? null),
            validThrough: $this->dateValue($hit['expiredAt'] ?? null),
            rawPayload: $hit,
        );
    }

    /** @param array<string, mixed> $hit */
    private function extractLocation(array $hit): ?string
    {
        $loc = $hit['location'] ?? null;
        if (!is_array($loc)) {
            return null;
        }

        $locality = $this->stringValue($loc, 'locality');
        $shortLabel = $this->stringValue($loc, 'shortLabel');
        $label = $this->stringValue($loc, 'label');

        return $locality ?? $shortLabel ?? $label;
    }

    /** @param array<string, mixed> $hit */
    private function extractContractType(array $hit): string
    {
        $contracts = $hit['contracts'] ?? null;
        if (is_array($contracts)) {
            if (in_array('contractor', $contracts, true)) {
                return 'FREELANCE';
            }
            if (in_array('permanent', $contracts, true)) {
                return 'CDI';
            }
            if (in_array('fixed-term', $contracts, true)) {
                return 'CDD';
            }
            if (in_array('apprenticeship', $contracts, true)) {
                return 'ALTERNANCE';
            }
            if (in_array('internship', $contracts, true)) {
                return 'STAGE';
            }
        }

        return 'FREELANCE';
    }

    /**
     * @param array<string, mixed> $hit
     *
     * @return array{?int, ?int}
     */
    private function extractSalary(array $hit): array
    {
        $minAnnual = $this->integerValue($hit['minAnnualSalary'] ?? null);
        $maxAnnual = $this->integerValue($hit['maxAnnualSalary'] ?? null);

        if ($minAnnual !== null || $maxAnnual !== null) {
            return [$minAnnual, $maxAnnual];
        }

        $minDaily = $this->integerValue($hit['minDailySalary'] ?? null);
        $maxDaily = $this->integerValue($hit['maxDailySalary'] ?? null);

        // Convert TJM to approximate annual (218 days/year)
        if ($minDaily !== null || $maxDaily !== null) {
            return [
                $minDaily !== null ? (int) round($minDaily * 218) : null,
                $maxDaily !== null ? (int) round($maxDaily * 218) : null,
            ];
        }

        return [null, null];
    }

    /** @param array<string, mixed> $hit */
    private function extractRemotePolicy(array $hit): ?string
    {
        $remoteMode = $this->stringValue($hit, 'remoteMode');
        if ($remoteMode === null) {
            return null;
        }

        return match (mb_strtolower($remoteMode)) {
            'full' => 'REMOTE',
            'partial', 'occasional' => 'HYBRID',
            'no' => 'ON_SITE',
            default => null,
        };
    }

    /** @param array<string, mixed> $hit */
    private function extractExperience(array $hit): ?int
    {
        $level = $this->stringValue($hit, 'experienceLevel');
        if ($level === null) {
            return null;
        }

        return match (mb_strtolower($level)) {
            'junior' => 1,
            'intermediate', 'confirmed' => 3,
            'senior' => 5,
            'expert' => 8,
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
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

    private function cleanText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ["\n", "\n", "\n", "\n\n", "\n"], $decoded));
        $cleaned = preg_replace('/\R{3,}/u', "\n\n", trim($stripped));

        return $cleaned !== '' ? $cleaned : null;
    }
}
