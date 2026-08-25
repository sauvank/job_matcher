<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Service\EsnDetector;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use PHPUnit\Framework\TestCase;

final class EsnDetectorTest extends TestCase
{
    private EsnDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new EsnDetector();
    }

    public function testItDetectsKnownEsnCompanies(): void
    {
        $knownCompanies = [
            'Capgemini',
            'Alten',
            'Sopra Steria',
            'CGI France',
            'Inetum',
            'Extia',
            'Zenika',
            'Octo Technology',
            'Ippon Technologies',
            'Wavestone',
            'Devoteam',
            'Aubay',
            'SII',
            'Davidson Consulting',
            'Akkodis',
            'Manpower',
            'Experis',
            'Michael Page',
            'Webnet',
            'Theodo',
            'Cat-Amania',
            'SFEIR',
            'Apside',
            'Ausy',
            'Squad',
            'Klanik',
            'MoOngy',
            'Cognizant',
            'Avanade',
        ];

        foreach ($knownCompanies as $company) {
            $offer = $this->createOffer(company: $company);
            self::assertTrue($this->detector->isEsn($offer), sprintf('Expected %s to be recognized as ESN', $company));
        }
    }

    public function testItDetectsCompanyKeywords(): void
    {
        $companiesWithKeywords = [
            'Tech Solutions Consulting',
            'Alpha Conseil Informatique',
            'Global IT Services',
            'Talent Recrutement',
            'Horizon Ingénierie Informatique',
            'ESN Digital Group',
            'Cabinet de Recrutement IT',
            'Agence Interim Tech',
        ];

        foreach ($companiesWithKeywords as $company) {
            $offer = $this->createOffer(company: $company);
            self::assertTrue($this->detector->isEsn($offer), sprintf('Expected %s to be recognized as ESN via keywords', $company));
        }
    }

    public function testItDetectsEsnPhrasesInDescription(): void
    {
        $phrases = [
            'Nous recrutons pour le compte de l\'un de nos clients grands comptes.',
            'Vous interviendrez en prestation chez notre client basé à Lyon.',
            'Poste en régie ou au forfait chez nos clients partenaires.',
            'Notre cabinet de recrutement recherche un profil senior pour son client.',
            'Rejoignez notre équipe de consultants passionnés au sein de notre ESN.',
            'Mission de délégation de compétences pour un client final.',
            'Notre client, leader dans le secteur bancaire, recherche un développeur.',
            'Mandaté par notre client pour renforcer son pôle technique.',
            'Intégré chez notre client, vous participerez aux développements.',
            'En tant que consultant Symfony, vous interviendrez chez des clients.',
        ];

        foreach ($phrases as $phrase) {
            $offer = $this->createOffer(company: 'Entreprise Anonyme', description: $phrase);
            self::assertTrue($this->detector->isEsn($offer), sprintf('Expected description "%s" to trigger ESN detection', $phrase));
        }
    }

    public function testItDetectsEsnFromRawPayload(): void
    {
        $offerCabinet = $this->createOffer(
            company: 'Société X',
            rawPayload: ['typeRecruteur' => 'CABINET'],
        );
        self::assertTrue($this->detector->isEsn($offerCabinet));

        $offerIntermediaire = $this->createOffer(
            company: 'Société Y',
            rawPayload: ['typeRecruteur' => 'ENTREPRISE_INTERMEDIAIRE'],
        );
        self::assertTrue($this->detector->isEsn($offerIntermediaire));
    }

    public function testItDoesNotFalselyFlagDirectEmployersAndPublicBodies(): void
    {
        $directEmployers = [
            'Doctolib',
            'BlaBlaCar',
            'Decathlon',
            'Airbus',
            'TotalEnergies',
            'L\'Oréal',
            'Société Générale',
            'BNP Paribas',
            'Ubisoft',
            'Deezer',
            'Conseil Régional d\'Auvergne-Rhône-Alpes',
            'Conseil Départemental de l\'Isère',
            'OpenClassrooms',
            'Dassault Systèmes',
            'Decathlon Digital',
        ];

        foreach ($directEmployers as $company) {
            $offer = $this->createOffer(
                company: $company,
                description: 'Rejoignez notre équipe technique interne pour développer notre plateforme SaaS produit.',
            );
            self::assertFalse($this->detector->isEsn($offer), sprintf('Expected %s not to be flagged as ESN', $company));
        }
    }

    /** @param array<string, mixed> $rawPayload */
    private function createOffer(
        ?string $company = null,
        ?string $title = 'Développeur Symfony',
        ?string $description = null,
        array $rawPayload = [],
    ): JobOffer {
        $source = new JobSource(new CandidateProfile(), 'Source', 'https://example.test/source', JobProviderType::HELLOWORK);
        $normalized = new NormalizedJobOffer(
            externalId: 'ext-'.bin2hex(random_bytes(4)),
            url: 'https://example.test/jobs/123',
            title: $title ?? 'Développeur Symfony',
            company: $company,
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: $description,
            publishedAt: null,
            validThrough: null,
            rawPayload: $rawPayload,
        );

        return new JobOffer($source, $normalized);
    }
}
