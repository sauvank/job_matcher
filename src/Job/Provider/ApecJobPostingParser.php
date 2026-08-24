<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Translation\JobMessage;
use Symfony\Component\DomCrawler\Crawler;

final class ApecJobPostingParser
{
    /** @return list<string> */
    public function extractOfferUrls(string $content, int $limit): array
    {
        $urls = [];

        // 1. Try DOM links
        $crawler = new Crawler($content);
        foreach ($crawler->filterXPath('//a[contains(@href, "/detail-offre/")]') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            if (preg_match('~/detail-offre/([a-zA-Z0-9]+)~', $href, $matches) === 1) {
                $urls[] = sprintf('https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/%s', $matches[1]);
                $urls = array_values(array_unique($urls));
                if (count($urls) >= $limit) {
                    return $urls;
                }
            }
        }

        // 2. Try JSON / script data regex
        if (count($urls) < $limit && preg_match_all('~["\'](?:numeroOffre|idOffre|id)["\']\s*:\s*["\']([0-9]{6,10}[A-Za-z]?)["\']~', $content, $matches)) {
            foreach ($matches[1] as $id) {
                $urls[] = sprintf('https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/%s', $id);
                $urls = array_values(array_unique($urls));
                if (count($urls) >= $limit) {
                    break;
                }
            }
        }

        return $urls;
    }

    /** @param array<string, mixed> $data */
    public function parseOfferFromApi(array $data): NormalizedJobOffer
    {
        $externalId = (string) ($data['numeroOffre'] ?? $data['id'] ?? '');
        if ($externalId === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $url = sprintf('https://www.apec.fr/candidat/recherche-emploi.html/emploi/detail-offre/%s', $externalId);
        $title = (string) ($data['intitule'] ?? '');
        if ($title === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $minSalary = null;
        $maxSalary = null;
        $salaryText = (string) ($data['salaireTexte'] ?? '');
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*k€/i', $salaryText, $salaryMatches)) {
            $minSalary = (int) $salaryMatches[1] * 1000;
            $maxSalary = (int) $salaryMatches[2] * 1000;
        } elseif (preg_match('/(?:partir de|minimum)\s*(\d+)\s*k€/i', $salaryText, $salaryMatches)) {
            $minSalary = (int) $salaryMatches[1] * 1000;
        }

        $description = $this->cleanHtml($data['texteOffre'] ?? null);

        return new NormalizedJobOffer(
            externalId: $externalId,
            url: $url,
            title: $title,
            company: $this->stringValue($data, 'nomCommercial'),
            location: $this->stringValue($data, 'lieuTexte'),
            contractType: 'CDI',
            minimumSalary: $minSalary,
            maximumSalary: $maxSalary,
            remotePolicy: preg_match('/télétravail|remote/i', (string) ($data['texteOffre'] ?? '')) === 1 ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: $this->extractYearsOfExperience(null, $description),
            description: $description,
            publishedAt: $this->dateValue($data['datePublication'] ?? null),
            validThrough: null,
            rawPayload: $data,
        );
    }

    public function parseOffer(string $html, string $url): NormalizedJobOffer
    {
        if (preg_match('~/detail-offre/([a-zA-Z0-9]+)~', $url, $matches) !== 1) {
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

        $titleNode = $crawler->filter('h1, .job-title, .offer-title');
        $title = $titleNode->count() > 0 ? trim($titleNode->first()->text()) : null;
        if ($title === null || $title === '') {
            throw new \RuntimeException(JobMessage::JOB_POSTING_NOT_FOUND);
        }

        $companyNode = $crawler->filter('.nom-entreprise, .company-name, .job-company');
        $company = $companyNode->count() > 0 ? trim($companyNode->first()->text()) : null;

        $locationNode = $crawler->filter('.job-location, .lieux, .offer-location');
        $location = $locationNode->count() > 0 ? trim($locationNode->first()->text()) : null;

        $descriptionNode = $crawler->filter('.job-description, .details-offer, .texte-offre, main');
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
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: preg_match('/télétravail|remote/i', $html) === 1 ? 'REMOTE_AVAILABLE' : null,
            yearsOfExperience: $this->extractYearsOfExperience(null, $description),
            description: $description,
            publishedAt: null,
            validThrough: null,
            rawPayload: ['source' => 'apec_html_fallback'],
        );
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
