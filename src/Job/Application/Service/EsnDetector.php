<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Job\Entity\JobOffer;

final class EsnDetector
{
    /**
     * Known ESNs, IT consulting firms, engineering services, and tech staffing agencies (normalized lowercase).
     *
     * @var list<string>
     */
    private const KNOWN_ESN_COMPANIES = [
        'accenture',
        'acensi',
        'adeliance',
        'adentis',
        'adecco',
        'advans group',
        'akkodis',
        'akka',
        'akka technologies',
        'alan allman',
        'alan allman associates',
        'alten',
        'alten sir',
        'alten delivery center',
        'altran',
        'altran technologies',
        'amaris',
        'amaris consulting',
        'approach people',
        'apside',
        'aquent',
        'asi',
        'asi informatique',
        'astek',
        'asten',
        'assystem',
        'atos',
        'aubay',
        'ausy',
        'avisto',
        'badenoch + clark',
        'bearingpoint',
        'beijaflore',
        'bull',
        'capgemini',
        'capgemini engineering',
        'cbtw',
        'celad',
        'cgi',
        'cgi france',
        'clever connect',
        'consort',
        'consort nt',
        'consort group',
        'd2si',
        'daveo',
        'davidson',
        'davidson consulting',
        'davidson si',
        'deloitte',
        'devoteam',
        'devoteam g cloud',
        'devoteam revolv',
        'digora',
        'econocom',
        'effiit',
        'elsys design',
        'ernst & young',
        'esn tech solutions',
        'eviden',
        'expectra',
        'experis',
        'expertime',
        'expleo',
        'expleo group',
        'externatic',
        'extia',
        'ey',
        'fed it',
        'fed group',
        'gfi',
        'gfi informatique',
        'hardis',
        'hardis group',
        'hays',
        'hays france',
        'headmind partners',
        'hn services',
        'inetum',
        'infotel',
        'infotel conseil',
        'ippon',
        'ippon technologies',
        'it link',
        'keyrus',
        'klanik',
        'klee',
        'klee group',
        'kpmg',
        'leeto tech',
        'lesjeudis',
        'leyton',
        'lhh',
        'linkt',
        'lynx rh',
        'maltem',
        'maltem consulting',
        'manpower',
        'maten',
        'maten it',
        'mc2i',
        'mc2i groupe',
        'meritis',
        'metanext',
        'michael page',
        'micropole',
        'modis',
        'neo soft',
        'neo-soft',
        'néo-soft',
        'neurones',
        'neurones it',
        'niji',
        'novertys',
        'nteq',
        'objectware',
        'octo',
        'octo technology',
        'onepoint',
        'open',
        'orange business services',
        'orange cyberdefense',
        'oresys',
        'page personnel',
        'positive thinking company',
        'pricewaterhousecoopers',
        'proservia',
        'proxiad',
        'pwc',
        'quanteam',
        'randstad',
        'robert half',
        'robert walters',
        'scalian',
        'sedona',
        'septentrion finance',
        'seyos',
        'sfeir',
        'sia partners',
        'siderlog',
        'sidiese',
        'groupe sidiese',
        'sii',
        'groupe sii',
        'silkhom',
        'smile',
        'smile open source',
        'sogeti',
        'sogeti high tech',
        'solutec',
        'sopra',
        'sopra banking',
        'sopra hr software',
        'sopra steria',
        'spring',
        'sqli',
        'sully group',
        'sword',
        'sword group',
        'synchrone',
        'synechron',
        'synetis',
        't&s',
        't&s engineering',
        'talan',
        'talentsoft',
        'technology & strategy',
        'tenacy',
        'umanis',
        'upstone',
        'viseo',
        'wavestone',
        'weborama',
        'wemanity',
        'wescale',
        'witekio',
        'worldline',
        'zenika',
    ];

    /**
     * Patterns matching company names that denote ESNs / consulting / recruitment.
     *
     * @var list<string>
     */
    private const COMPANY_PATTERNS = [
        '/\b(?:esn|ssii)\b/iu',
        '/\bconsulting\b/iu',
        '/\bconseil(?:s)?\b/iu',
        '/\bing[eé]nierie\b/iu',
        '/\bdigital\s+services?\b/iu',
        '/\b(?:recrutement|recruitment|staffing|chasseur\s+de\s+t[eê]tes?)\b/iu',
        '/\b(?:infog[eé]rance|prestation(?:s)?)\b/iu',
        '/\bservices?\s+num[eé]riques?\b/iu',
        '/\btechnology\s+consulting\b/iu',
        '/\bit\s+services?\b/iu',
        '/\bconseil\s+en\s+technologies?\b/iu',
        '/\bcabinet\s+de\s+conseil\b/iu',
        '/\bconseil\s+it\b/iu',
        '/\bconseil\s+et\s+ing[eé]nierie\b/iu',
        '/\bservices\s+informatiques?\b/iu',
    ];

    /**
     * Patterns matching description and title keywords characteristic of client placement / consulting.
     *
     * @var list<string>
     */
    private const CONTENT_PATTERNS = [
        '/\bpour\s+le\s+compte\s+d[\'’]un\s+(?:de\s+nos\s+)?clients?\b/iu',
        '/\bpour\s+l[\'’]un\s+de\s+nos\s+clients\b/iu',
        '/\bpour\s+notre\s+client\b/iu',
        '/\bchez\s+(?:notre|nos|l[\'’]un\s+de\s+nos)\s+clients?\b/iu',
        '/\b(?:en\s+prestation|en\s+r[eé]gie|au\s+forfait)\b/iu',
        '/\bd[eé]l[eé]gation\s+de\s+comp[eé]tences?\b/iu',
        '/\bassistance\s+technique\b/iu',
        '/\bsoci[eé]t[eé]\s+de\s+services?\b/iu',
        '/\b(?:entreprise|soci[eé]t[eé])\s+de\s+services?\s+du\s+num[eé]rique\b/iu',
        '/\bcabinet\s+de\s+recrutement\b/iu',
        '/\bcabinet\s+de\s+conseil\b/iu',
        '/\b(?:notre|une)\s+esn\b/iu',
        '/\b(?:notre|une)\s+ssii\b/iu',
        '/\b(?:nos|vos)\s+consultants?\b/iu',
        '/\brejoindre\s+(?:notre|nos)\s+(?:consultants?|[eé]quipes?\s+de\s+consultants?)\b/iu',
        '/\bclients?\s+grands?\s+comptes?\b/iu',
        '/\bclient\s+final\b/iu',
        '/\bintervenir\s+chez\s+(?:nos|des)\s+clients\b/iu',
        '/\bmissions?\s+chez\s+nos\s+clients\b/iu',
    ];

    public static function detect(JobOffer $offer): bool
    {
        return (new self())->isEsn($offer);
    }

    public function isEsn(JobOffer $offer): bool
    {
        $company = $offer->getCompany();
        if ($company !== null && $this->isEsnCompany($company)) {
            return true;
        }

        if ($this->hasEsnInRawPayload($offer->getRawPayload())) {
            return true;
        }

        $description = $offer->getDescription();
        if ($description !== null && $this->hasEsnContent($description)) {
            return true;
        }

        $title = $offer->getTitle();
        if ($this->hasEsnContent($title)) {
            return true;
        }

        return false;
    }

    public function isEsnCompany(string $company): bool
    {
        $normalized = $this->normalizeText($company);
        if ($normalized === '') {
            return false;
        }

        foreach (self::KNOWN_ESN_COMPANIES as $known) {
            if ($normalized === $known || str_starts_with($normalized, $known.' ') || str_ends_with($normalized, ' '.$known)) {
                return true;
            }
        }

        foreach (self::COMPANY_PATTERNS as $pattern) {
            if (preg_match($pattern, $company) === 1) {
                return true;
            }
        }

        return false;
    }

    public function hasEsnContent(string $text): bool
    {
        foreach (self::CONTENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $payload */
    private function hasEsnInRawPayload(array $payload): bool
    {
        $typeRecruteur = $payload['typeRecruteur'] ?? null;
        if (is_string($typeRecruteur)) {
            $upper = strtoupper(trim($typeRecruteur));
            if ($upper === 'CABINET' || $upper === 'ENTREPRISE_INTERMEDIAIRE' || str_contains($upper, 'ESN')) {
                return true;
            }
        }

        $secteur = $payload['secteurActivite'] ?? $payload['domaine'] ?? null;
        if (is_string($secteur)) {
            $normalizedSecteur = $this->normalizeText($secteur);
            if (str_contains($normalizedSecteur, 'conseil en systemes')
                || str_contains($normalizedSecteur, 'services informatiques')
                || str_contains($normalizedSecteur, 'travail temporaire')
                || str_contains($normalizedSecteur, 'placement de personnel')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($transliterated !== false) {
            $text = $transliterated;
        }
        $cleaned = preg_replace('/[^a-z0-9]+/i', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $cleaned) ?? $cleaned);
    }
}
