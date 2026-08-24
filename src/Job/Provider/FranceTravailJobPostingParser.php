<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Translation\JobMessage;
use Symfony\Component\DomCrawler\Crawler;

final class FranceTravailJobPostingParser
{
    /** @return list<string> */
    public function extractOfferUrls(string $content, int $limit): array
    {
        $urls = [];
        $crawler = new Crawler($content);

        // 1. Links with /offres/recherche/detail/
        foreach ($crawler->filterXPath('//a[contains(@href, "/offres/recherche/detail/")]') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            if (preg_match('~/offres/recherche/detail/([a-zA-Z0-9]+)~', $href, $matches) === 1) {
                $urls[] = sprintf('https://candidat.francetravail.fr/offres/recherche/detail/%s', $matches[1]);
                $urls = array_values(array_unique($urls));
                if (count($urls) >= $limit) {
                    return $urls;
                }
            }
        }

        // 2. Fallback to data-id-offre or regex
        if (count($urls) < $limit && preg_match_all('~data-id-offre=["\']([a-zA-Z0-9]{5,10})["\']~', $content, $matches)) {
            foreach ($matches[1] as $id) {
                $urls[] = sprintf('https://candidat.francetravail.fr/offres/recherche/detail/%s', $id);
                $urls = array_values(array_unique($urls));
                if (count($urls) >= $limit) {
                    break;
                }
            }
        }

        return $urls;
    }

    public function parseOffer(string $html, string $url): NormalizedJobOffer
    {
        if (preg_match('~/detail/([a-zA-Z0-9]+)~', $url, $matches) !== 1) {
            throw new \RuntimeException(JobMessage::INVALID_URL);
        }
        $externalId = $matches[1];

        $posting = $this->findJobPostingJsonLd($html);
        if ($posting !== null) {
            return $this->buildOfferFromJsonLd($posting, $externalId, $url, $html);
        }

        return $this->buildOfferFromHtml($html, $externalId, $url);
    }

    /** @return array<string, mixed>|null */
    private function findJobPostingJsonLd(string $html): ?array
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

        return null;
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
    private function buildOfferFromJsonLd(array $posting, string $externalId, string $url, string $html): NormalizedJobOffer
    {
        $title = $posting['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $baseSalary = $posting['baseSalary'] ?? null;
        $salary = is_array($baseSalary) ? ($baseSalary['value'] ?? null) : null;
        $organization = $posting['hiringOrganization'] ?? null;
        $experience = $posting['experienceRequirements'] ?? null;
        $description = $this->cleanHtml($posting['description'] ?? null);

        return new NormalizedJobOffer(
            externalId: $externalId,
            url: $url,
            title: trim($title),
            company: $this->stringValue($organization, 'name'),
            location: $this->extractLocation($posting),
            contractType: $this->extractContractType($html, $posting),
            minimumSalary: $this->integerValue($salary, 'minValue'),
            maximumSalary: $this->integerValue($salary, 'maxValue'),
            remotePolicy: ($posting['jobLocationType'] ?? null) === 'TELECOMMUTE' ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: $this->extractYearsOfExperience($experience, $description),
            description: $description,
            publishedAt: $this->dateValue($posting['datePosted'] ?? null),
            validThrough: $this->dateValue($posting['validThrough'] ?? null),
            rawPayload: $posting,
        );
    }

    private function buildOfferFromHtml(string $html, string $externalId, string $url): NormalizedJobOffer
    {
        $crawler = new Crawler($html);

        $title = $this->firstNodeValue($crawler, ['[itemprop="title"]', 'h1', '.item-title']);
        if ($title === null || $title === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }
        $title = (string) preg_replace('/^Offre\s+n°\s*[a-zA-Z0-9]+\s*/iu', '', $title);

        $company = $this->firstNodeValue($crawler, [
            '[itemprop="hiringOrganization"] [itemprop="name"]',
            '[itemprop="hiringOrganization"]',
            '.employer-name',
        ]);
        if ($company !== null) {
            $company = preg_replace('/(?:\s*-\s*)?Localiser\s+avec\s+Mappy/iu', '', $company);
            $company = is_string($company) && trim($company) !== '' ? trim($company) : null;
        }

        $location = $this->firstNodeValue($crawler, [
            '[itemprop="jobLocation"] [itemprop="name"]',
            '[itemprop="addressLocality"]',
            '[itemprop="jobLocation"]',
            '.location',
        ]);

        $descriptionNode = $crawler->filter('[itemprop="description"], .description, .modal-details');
        $description = $descriptionNode->count() > 0 ? $this->cleanHtml($descriptionNode->first()->html()) : null;

        $contractType = null;
        if (preg_match('/(CDI|CDD|Freelance|Stage|Alternance|Intérim)/i', $html, $contractMatches)) {
            $contractType = mb_strtoupper($contractMatches[1]);
        }

        return new NormalizedJobOffer(
            externalId: $externalId,
            url: $url,
            title: $title,
            company: $company,
            location: $location,
            contractType: $contractType,
            minimumSalary: $this->nodeIntegerValue($crawler, '[itemprop="baseSalary"] [itemprop="minValue"]'),
            maximumSalary: $this->nodeIntegerValue($crawler, '[itemprop="baseSalary"] [itemprop="maxValue"]'),
            remotePolicy: preg_match('/télétravail|remote/i', $html) === 1 ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: $this->extractYearsOfExperience(
                $this->firstNodeValue($crawler, ['[itemprop="experienceRequirements"]']),
                $description,
            ),
            description: $description,
            publishedAt: $this->nodeDateValue($crawler, '[itemprop="datePosted"]'),
            validThrough: $this->nodeDateValue($crawler, '[itemprop="validThrough"]'),
            rawPayload: ['source' => 'francetravail_html_fallback'],
        );
    }

    /** @param list<string> $selectors */
    private function firstNodeValue(Crawler $crawler, array $selectors): ?string
    {
        foreach ($selectors as $selector) {
            $nodes = $crawler->filter($selector);
            if ($nodes->count() === 0) {
                continue;
            }

            $node = $nodes->first();
            $value = $node->attr('content');
            if (!is_string($value) || trim($value) === '') {
                $value = $node->text('');
            }
            if (trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function nodeIntegerValue(Crawler $crawler, string $selector): ?int
    {
        $value = $this->firstNodeValue($crawler, [$selector]);

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function nodeDateValue(Crawler $crawler, string $selector): ?\DateTimeImmutable
    {
        return $this->dateValue($this->firstNodeValue($crawler, [$selector]));
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
        if (isset($posting['employmentType']) && is_string($posting['employmentType']) && trim($posting['employmentType']) !== '') {
            $type = trim($posting['employmentType']);
            if (strcasecmp($type, 'FULL_TIME') === 0 || strcasecmp($type, 'PERMANENT') === 0) {
                return 'CDI';
            }
            if (strcasecmp($type, 'CONTRACTOR') === 0 || strcasecmp($type, 'TEMPORARY') === 0) {
                return 'CDD';
            }

            return $type;
        }

        if (preg_match('/(CDI|CDD|Freelance|Stage|Alternance|Intérim)/i', $html, $matches)) {
            return mb_strtoupper($matches[1]);
        }

        return null;
    }

    private function extractYearsOfExperience(mixed $experience, ?string $text): ?int
    {
        $years = [];

        if (is_array($experience)) {
            $months = $experience['monthsOfExperience'] ?? null;
            if (is_int($months) || is_float($months)) {
                $years[] = (int) ceil($months / 12);
            }
        }

        if ($text !== null) {
            foreach (['~(\d+)\s+(?:ans?|années?)\s+d[\'’]expérience~iu', '~expérience\s+(?:minimum\s+)?de\s+(\d+)\s+(?:ans?|années?)~iu'] as $pattern) {
                preg_match_all($pattern, $text, $matches);
                foreach ($matches[1] as $textualYears) {
                    $years[] = (int) $textualYears;
                }
            }
        }

        return $years === [] ? null : max($years);
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
