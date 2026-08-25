<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Job\Entity\JobOffer;
use Symfony\Component\String\Slugger\AsciiSlugger;

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
        'agap2',
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
        'asi informatique',
        'astek',
        'asten',
        'assystem',
        'atos',
        'aubay',
        'ausy',
        'avanade',
        'avisto',
        'axians',
        'badenoch & clark',
        'badenoch + clark',
        'bearingpoint',
        'beijaflore',
        'bertrandt',
        'business & decision',
        'bull sas',
        'bull atos',
        'capgemini',
        'capgemini engineering',
        'cat amania',
        'cbtw',
        'cegedim',
        'celad',
        'cgi',
        'cgi france',
        'claranet',
        'clever connect',
        'cognizant',
        'computacenter',
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
        'dxc technology',
        'econocom',
        'effiit',
        'elsys design',
        'emagine',
        'epam',
        'epam systems',
        'ernst & young',
        'esn tech solutions',
        'eviden',
        'exakis nelite',
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
        'freelance com',
        'gfi',
        'gfi informatique',
        'groupe open',
        'groupe sii',
        'groupe sidiese',
        'hardis',
        'hardis group',
        'hays',
        'hays france',
        'hcl tech',
        'hcl technologies',
        'headmind partners',
        'hn services',
        'inetum',
        'infosys',
        'infotel',
        'infotel conseil',
        'ippon',
        'ippon technologies',
        'it link',
        'keyrus',
        'klanik',
        'klee group',
        'kpmg',
        'leeto tech',
        'lesjeudis',
        'leyton',
        'lhh',
        'lynx rh',
        'magellan partners',
        'maltem',
        'maltem consulting',
        'manpower',
        'mantu',
        'maten',
        'maten it',
        'mca ingenierie',
        'mc2i',
        'mc2i groupe',
        'meritis',
        'metanext',
        'michael page',
        'micropole',
        'modis',
        'moongy',
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
        'open sas',
        'orange business services',
        'orange cyberdefense',
        'oresys',
        'page personnel',
        'pentalog',
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
        'segula',
        'segula technologies',
        'septentrion finance',
        'seyos',
        'sfeir',
        'sia partners',
        'siderlog',
        'sidiese',
        'sii',
        'silkhom',
        'smile open source',
        'sogeti',
        'sogeti high tech',
        'solutec',
        'sopra',
        'sopra banking',
        'sopra hr software',
        'sopra steria',
        'spie ics',
        'spring france',
        'spring professional',
        'squad',
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
        'tata consultancy services',
        'tcs',
        'technology & strategy',
        'tenacy',
        'theodo',
        'tibco software',
        'umanis',
        'upstone',
        'viseo',
        'wavestone',
        'weborama',
        'webnet',
        'wemanity',
        'wescale',
        'wipro',
        'witekio',
        'worldline',
        'zenika',
    ];

    /**
     * Patterns matching company names that are definitely NOT ESNs (guardrails against false positives).
     *
     * @var list<string>
     */
    private const EXCLUDED_COMPANY_PATTERNS = [
        '/\bconseil\s+(?:r[eé]gional|d[eé]partemental|g[eé]n[eé]ral|d[\'’][eé]tat|national|sup[eé]rieur|constitutionnel|des\s+prud[\'’]hommes|d[\'’]architecture|scientifique|de\s+l[\'’]europe)\b/iu',
        '/\b(?:openclassrooms|dassault\s+syst[eè]mes|decathlon\s+digital|renault\s+digital)\b/iu',
    ];

    /**
     * Patterns matching company names that denote ESNs / consulting / recruitment.
     *
     * @var list<string>
     */
    private const COMPANY_PATTERNS = [
        '/\b(?:esn|ssii)\b/iu',
        '/\bconsulting\b/iu',
        '/\bcabinet\s+de\s+(?:conseil|recrutement|chasse|recruiting)\b/iu',
        '/\b(?:recrutement|recruitment|staffing|chasseur\s+de\s+t[eê]tes?|int[eé]rim|travail\s+temporaire|agence\s+d[\'’]emploi)\b/iu',
        '/\bconseil\s+(?:en\s+technologies?|it|informatique|en\s+syst[eè]mes?|en\s+ing[eé]nierie|en\s+strat[eé]gie|en\s+transformation\s+digitale|en\s+management)\b/iu',
        '/\bconseil\s+et\s+ing[eé]nierie\b/iu',
        '/\bservices?\s+num[eé]riques?\b/iu',
        '/\bdigital\s+services?\b/iu',
        '/\btechnology\s+services?\b/iu',
        '/\bit\s+services?\b/iu',
        '/\bservices?\s+informatiques?\b/iu',
        '/\btech\s+staffing\b/iu',
        '/\bing[eé]nierie\s+(?:informatique|logicielle|et\s+conseil|technologique|des\s+syst[eè]mes|et\s+technologies)\b/iu',
    ];

    /**
     * Patterns matching description and title keywords characteristic of client placement / consulting.
     *
     * @var list<string>
     */
    private const CONTENT_PATTERNS = [
        '/\bpour\s+le\s+compte\s+(?:d[\'’]|de\s+l[\'’]|de\s+)?(?:l[\'’]un\s+de\s+nos|un\s+de\s+nos|notre|nos|son|un|d[\'’]un)?\s*clients?\b/iu',
        '/\bpour\s+(?:l[\'’]un\s+de\s+nos|un\s+de\s+nos|notre|l[\'’]un\s+des|un\s+des)\s+clients?\b/iu',
        '/\b(?:recrut(?:e|ons|ement)|recherch(?:e|ons))\s+pour\s+(?:le\s+compte\s+d[\'’]|le\s+compte\s+de\s+l[\'’]|le\s+compte\s+de\s+|l[\'’]un\s+de\s+nos|un\s+de\s+nos|notre|son|un|nos)\s*clients?\b/iu',
        '/\bmandat[eé](?:s|e)?\s+par\s+(?:l[\'’]un\s+de\s+nos|un\s+de\s+nos|notre|un|son)\s+clients?\b/iu',
        '/\bnotre\s+client,?\s+(?:un\s+|une\s+|grand\s+|acteur\s+|leader\s+|sp[eé]cialis[eé]|pure\s+player|soci[eé]t[eé]|groupe|pme|startup|cabinet|dans\s+le\s+secteur)\b/iu',
        '/\bchez\s+(?:notre|nos|l[\'’]un\s+de\s+nos|un\s+de\s+nos)\s+clients?\b/iu',
        '/\bintervenir\s+chez\s+(?:notre|nos|des|les)\s+clients?\b/iu',
        '/\bmissions?\s+(?:chez\s+(?:notre|nos|des|les)\s+clients?|en\s+client[eè]le)\b/iu',
        '/\bint[eé]gr[eé](?:e)?\s+chez\s+(?:notre|le|un)\s+client\b/iu',
        '/\b(?:en\s+prestation|en\s+r[eé]gie|au\s+forfait\s+ou\s+en\s+r[eé]gie|en\s+mode\s+forfait|en\s+assistance\s+technique\s+chez)\b/iu',
        '/\bd[eé]l[eé]gation\s+de\s+(?:comp[eé]tences?|personnel|ressources?)\b/iu',
        '/\bmise\s+[aà]\s+disposition\s+de\s+(?:comp[eé]tences?|personnel|consultants?)\b/iu',
        '/\b(?:notre|une)\s+(?:esn|ssii)\b/iu',
        '/\b(?:entreprise|soci[eé]t[eé])\s+de\s+services?\s+du\s+num[eé]rique\b/iu',
        '/\bcabinet\s+de\s+recrutement\b/iu',
        '/\bcabinet\s+de\s+conseil\s+(?:en\s+technologies?|it|en\s+syst[eè]mes?|en\s+ing[eé]nierie|en\s+strat[eé]gie|en\s+transformation\s+digitale)\b/iu',
        '/\bchasseur\s+de\s+t[eê]tes?\b/iu',
        '/\bagence\s+d[\'’]int[eé]rim\b/iu',
        '/\bsoci[eé]t[eé]\s+d[\'’]ing[eé]nierie\s+et\s+de\s+conseil\b/iu',
        '/\b(?:rejoindre|int[eé]grer)\s+(?:notre|nos)\s+(?:consultants?|[eé]quipes?\s+de\s+consultants?|communaut[eé]\s+de\s+consultants?)\b/iu',
        '/\ben\s+tant\s+que\s+consultant(?:e)?\s+(?:symfony|php|java|devops|fullstack|back|front|cloud|data|lead|architecte|it|technique)\b/iu',
        '/\bnos\s+consultants?\s+(?:interviennent|accompagnent|r[eé]alisent)\b/iu',
    ];

    public static function detect(JobOffer $offer): bool
    {
        return (new self())->isEsn($offer);
    }

    public function isEsn(JobOffer $offer): bool
    {
        $company = $offer->getCompany();
        if ($company !== null) {
            if ($this->isExcludedCompany($company)) {
                return false;
            }

            if ($this->isEsnCompany($company)) {
                return true;
            }
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
        if ($this->isExcludedCompany($company)) {
            return false;
        }

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

    public function isExcludedCompany(string $company): bool
    {
        foreach (self::EXCLUDED_COMPANY_PATTERNS as $pattern) {
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
            if ($upper === 'CABINET'
                || $upper === 'ENTREPRISE_INTERMEDIAIRE'
                || str_contains($upper, 'ESN')
                || str_contains($upper, 'SSII')
                || str_contains($upper, 'RECRUTEMENT')
                || str_contains($upper, 'INTERIM')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(string $text): string
    {
        $slug = (new AsciiSlugger())->slug($text, ' ')->lower()->toString();

        return trim(preg_replace('/\s+/', ' ', $slug) ?? $slug);
    }
}
