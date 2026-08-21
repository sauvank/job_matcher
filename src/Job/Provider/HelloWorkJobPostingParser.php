<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Translation\JobMessage;
use Symfony\Component\DomCrawler\Crawler;

final class HelloWorkJobPostingParser
{
    /** @return list<string> */
    public function extractOfferUrls(string $html, int $limit): array
    {
        $crawler = new Crawler($html);
        $urls = [];

        foreach ($crawler->filterXPath('//a[contains(@href, "/fr-fr/emplois/")]') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            if (preg_match('~^/fr-fr/emplois/(\d+)\.html$~', $href) !== 1) {
                continue;
            }

            $urls[] = 'https://www.hellowork.com'.$href;
            $urls = array_values(array_unique($urls));

            if (count($urls) >= $limit) {
                break;
            }
        }

        return $urls;
    }

    public function parseOffer(string $html, string $url): NormalizedJobOffer
    {
        $posting = $this->extractJobPosting($html);
        $title = $posting['title'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        if (preg_match('~/emplois/(\d+)\.html~', $url, $matches) !== 1) {
            throw new \RuntimeException(JobMessage::INVALID_URL);
        }

        $baseSalary = $posting['baseSalary'] ?? null;
        $salary = is_array($baseSalary) ? ($baseSalary['value'] ?? null) : null;
        $organization = $posting['hiringOrganization'] ?? null;
        $experience = $posting['experienceRequirements'] ?? null;

        return new NormalizedJobOffer(
            externalId: $matches[1],
            url: $url,
            title: trim($title),
            company: $this->stringValue($organization, 'name'),
            location: $this->extractLocation($posting),
            contractType: $this->extractContractType($html, $posting),
            minimumSalary: $this->integerValue($salary, 'minValue'),
            maximumSalary: $this->integerValue($salary, 'maxValue'),
            remotePolicy: ($posting['jobLocationType'] ?? null) === 'TELECOMMUTE' ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: $this->extractYearsOfExperience($experience),
            description: $this->cleanHtml($posting['description'] ?? null),
            publishedAt: $this->dateValue($posting['datePosted'] ?? null),
            validThrough: $this->dateValue($posting['validThrough'] ?? null),
            rawPayload: $posting,
        );
    }

    /** @return array<string, mixed> */
    private function extractJobPosting(string $html): array
    {
        $crawler = new Crawler($html);

        foreach ($crawler->filterXPath('//script[@type="application/ld+json"]') as $script) {
            $decoded = json_decode(trim($script->textContent), true);
            if (!is_array($decoded)) {
                continue;
            }

            $posting = $this->findJobPosting($decoded);
            if ($posting !== null) {
                return $posting;
            }
        }

        throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<string, mixed>|null
     */
    private function findJobPosting(array $value): ?array
    {
        if (($value['@type'] ?? null) === 'JobPosting') {
            /* @var array<string, mixed> $value */
            return $value;
        }

        foreach ($value as $child) {
            if (!is_array($child)) {
                continue;
            }

            $posting = $this->findJobPosting($child);
            if ($posting !== null) {
                return $posting;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $posting */
    private function extractLocation(array $posting): ?string
    {
        $location = $posting['jobLocation'] ?? null;
        $address = is_array($location) ? ($location['address'] ?? null) : null;
        if (!is_array($address)) {
            return null;
        }

        $parts = [];
        foreach (['addressLocality', 'postalCode'] as $key) {
            if (isset($address[$key]) && is_string($address[$key]) && trim($address[$key]) !== '') {
                $parts[] = trim($address[$key]);
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /** @param array<string, mixed> $posting */
    private function extractContractType(string $html, array $posting): ?string
    {
        if (preg_match('~"contrat":"([^"]+)"~u', $html, $matches) === 1) {
            return $matches[1];
        }

        return isset($posting['employmentType']) && is_string($posting['employmentType'])
            ? $posting['employmentType']
            : null;
    }

    private function extractYearsOfExperience(mixed $experience): ?int
    {
        if (!is_array($experience)) {
            return null;
        }

        $months = $experience['monthsOfExperience'] ?? null;
        if (!is_int($months) && !is_float($months)) {
            return null;
        }

        return (int) ceil($months / 12);
    }

    private function cleanHtml(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    private function dateValue(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** @param mixed[]|mixed $value */
    private function stringValue(mixed $value, string $key): ?string
    {
        return is_array($value) && isset($value[$key]) && is_string($value[$key]) ? $value[$key] : null;
    }

    /** @param mixed[]|mixed $value */
    private function integerValue(mixed $value, string $key): ?int
    {
        if (!is_array($value) || !isset($value[$key]) || (!is_int($value[$key]) && !is_float($value[$key]))) {
            return null;
        }

        return (int) round($value[$key]);
    }
}
