<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Repository\JobSourceRepository;
use App\Matching\DTO\AnalyzedRequirement;
use App\Matching\DTO\MatchScore;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Enum\RequirementAssessment;
use App\Matching\Enum\RequirementCategory;
use App\Matching\Enum\RequirementImportance;
use App\Matching\Repository\JobMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JobPagesControllerTest extends AuthenticatedWebTestCase
{
    public function testJobSourcePageIsAvailable(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $client->request('GET', '/sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sources d’offres');
        self::assertSelectorTextContains('.page-heading', 'Welcome to the Jungle');
        self::assertSelectorTextContains('.form-card', 'Ajouter une recherche');
    }

    public function testJobOfferPageIsAvailable(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Offres analysées');
        self::assertSelectorTextContains('.page-heading', 'compatibilité calculée par l’IA');
        self::assertSelectorExists('.view-tab.active');
        self::assertSelectorTextContains('.view-tab.active', 'Meilleurs scores');
        self::assertSelectorExists('a[href="/jobs?view=latest"]');
    }

    public function testJobOffersCanBeViewedByLatest(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — PHP — Lyon',
            'https://example.test/latest/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'latest-'.$uniqueId,
            url: 'https://example.test/latest/'.$uniqueId.'/offer',
            title: 'Offre récente '.$uniqueId,
            company: 'Entreprise Test',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: new \DateTimeImmutable('2026-08-25 08:00:00'),
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/jobs?view=latest');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-heading', 'date de découverte la plus récente');
        self::assertSelectorTextContains('.view-tab.active', 'Dernières annonces trouvées');
        self::assertSelectorTextContains('.offer-meta', 'Trouvée le');
    }

    public function testJobOffersDisplayEsnBadgeAndExcludeEsnDropdownFilter(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — ESN — Lyon',
            'https://example.test/esn/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $esnOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'esn-'.$uniqueId,
            url: 'https://example.test/esn/'.$uniqueId.'/offer',
            title: 'Développeur Fullstack',
            company: 'Capgemini',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Mission pour notre client grand compte',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $esnOffer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($esnOffer);
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[data-offer-filter-target="excludeEsn"]');
        self::assertSelectorExists('.offer-card[data-offer-filter-esn="1"]');
        self::assertSelectorTextContains('.offer-card[data-offer-filter-esn="1"] .badge-warning', 'ESN');

        $matchId = $match->getId();
        self::assertNotNull($matchId);
        $client->request('GET', '/jobs/'.$matchId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-meta .badge-warning', 'ESN');
    }

    public function testJobOffersCanBeFilteredByProviderInDropdown(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        foreach ([JobProviderType::HELLOWORK, JobProviderType::FRANCE_TRAVAIL] as $provider) {
            $source = new JobSource(
                $account->getCandidateProfile(),
                $provider->value.' — PHP — Lyon',
                'https://example.test/'.$provider->value.'/'.$uniqueId,
                $provider,
            );
            $offer = new JobOffer($source, new NormalizedJobOffer(
                externalId: $provider->value.'-'.$uniqueId,
                url: 'https://example.test/'.$provider->value.'/'.$uniqueId.'/offer',
                title: 'Offre '.$provider->value.' '.$uniqueId,
                company: 'Entreprise test',
                location: 'Lyon',
                contractType: 'CDI',
                minimumSalary: null,
                maximumSalary: null,
                remotePolicy: null,
                yearsOfExperience: null,
                description: 'Description de test',
                publishedAt: null,
                validThrough: null,
                rawPayload: [],
            ));
            $match = new JobMatch(
                $account->getCandidateProfile(),
                $offer,
                new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
            );
            $entityManager->persist($source);
            $entityManager->persist($offer);
            $entityManager->persist($match);
        }
        $entityManager->flush();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'input[data-offer-filter-target="sourceCheckbox"][value="HELLOWORK"]');
        self::assertSelectorCount(1, 'input[data-offer-filter-target="sourceCheckbox"][value="FRANCE_TRAVAIL"]');
        self::assertSelectorExists('.offer-card[data-offer-filter-value="HELLOWORK"]');
        self::assertSelectorExists('.offer-card[data-offer-filter-value="FRANCE_TRAVAIL"]');
        self::assertSelectorTextContains('.offer-card[data-offer-filter-value="FRANCE_TRAVAIL"]', 'France Travail');
    }

    public function testJobOffersExposeCompatibilityScoreSliderAndFilterAttributes(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — PHP — Lyon',
            'https://example.test/score-test/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'score-'.$uniqueId,
            url: 'https://example.test/score-test/'.$uniqueId.'/offer',
            title: 'Lead Dev PHP Symfony',
            company: 'Tech Company',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Poste avec score de compatibilité élevé',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(88, 88, 88, 88, 88, 88, 88, 88, 88, [], [], [], []),
        );
        $match->completeSemanticAnalysis(
            new SemanticJobAnalysis(
                compatibilityScore: 88,
                summary: 'Excellente correspondance',
                requirements: [
                    new AnalyzedRequirement(
                        category: RequirementCategory::TECHNICAL,
                        importance: RequirementImportance::REQUIRED,
                        label: 'PHP / Symfony',
                        offerEvidence: 'Maîtrise de PHP 8 et Symfony demandée',
                        assessment: RequirementAssessment::MATCH,
                        cvEvidence: '5 ans d’expérience Symfony',
                        explanation: 'Profil parfaitement aligné',
                    ),
                ],
                strengths: ['PHP 8', 'Symfony'],
                concerns: [],
                questions: [],
                jobSummary: 'Lead developer sur Symfony 7',
                keyExpectations: ['Architecture', 'Mentorat'],
                requiredCapacities: ['Symfony', 'PHP'],
            ),
            'gpt-4o-mini',
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[data-offer-filter-target="minScore"]');
        self::assertSelectorExists('[data-offer-filter-target="minScoreLabel"]');
        self::assertSelectorExists('.filter-preset-btn[data-score="80"]');
        self::assertSelectorExists('.offer-card[data-offer-filter-score="88"]');
    }

    public function testJobSourcesExposeOneFilterTabPerSearchLabel(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));
        $backendLabel = 'Backend '.$uniqueId;
        $frontendLabel = 'Frontend '.$uniqueId;

        $entityManager->persist(new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — '.$backendLabel.' — Lyon',
            'https://example.test/hellowork-'.$uniqueId,
            JobProviderType::HELLOWORK,
        ));
        $entityManager->persist(new JobSource(
            $account->getCandidateProfile(),
            'Apec — '.$backendLabel.' — Lyon',
            'https://example.test/apec-'.$uniqueId,
            JobProviderType::APEC,
        ));
        $entityManager->persist(new JobSource(
            $account->getCandidateProfile(),
            'France Travail — '.$frontendLabel.' — Lyon',
            'https://example.test/france-travail-'.$uniqueId,
            JobProviderType::FRANCE_TRAVAIL,
        ));
        $entityManager->flush();

        $client->request('GET', '/sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, sprintf('.source-filter-tab[data-source-filter-value="%s"]', $backendLabel));
        self::assertSelectorCount(1, sprintf('.source-filter-tab[data-source-filter-value="%s"]', $frontendLabel));
        self::assertSelectorCount(2, sprintf('tr[data-source-filter-value="%s"]', $backendLabel));
        self::assertSelectorCount(1, sprintf('tr[data-source-filter-value="%s"]', $frontendLabel));
    }

    public function testAJobSourceCanBeDeleted(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $source = new JobSource($account->getCandidateProfile(), 'Recherche à supprimer', 'https://example.test/jobs/delete-me', JobProviderType::HELLOWORK);
        $entityManager->persist($source);
        $entityManager->flush();
        $sourceId = $source->getId();
        self::assertNotNull($sourceId);

        $crawler = $client->request('GET', '/sources');
        $form = $crawler->filter(sprintf('form[action="/sources/%d/delete"]', $sourceId))->form();
        $client->submit($form, [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $responseContent = $client->getResponse()->getContent();
        self::assertIsString($responseContent);
        self::assertStringContainsString(sprintf('target="job-source-%d"', $sourceId), $responseContent);
        self::assertStringContainsString('supprimées', $responseContent);
        $entityManager->clear();
        $repository = self::getContainer()->get(JobSourceRepository::class);
        self::assertInstanceOf(JobSourceRepository::class, $repository);
        self::assertNull($repository->find($sourceId));
    }

    public function testAnotherAccountsSourcesAndMatchesAreHidden(): void
    {
        $client = self::createClient();
        $owner = $this->owner();
        $otherAccount = $this->account('other-jobs@example.test');
        $client->loginUser($owner);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(6));

        $source = new JobSource(
            $otherAccount->getCandidateProfile(),
            'Recherche privée autre compte',
            'https://example.test/jobs/private-other-account-'.$uniqueId,
            JobProviderType::FAKE,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'private-other-account-'.$uniqueId,
            url: 'https://example.test/jobs/private-other-account-'.$uniqueId.'/offer',
            title: 'Offre privée autre compte',
            company: 'Entreprise privée',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description privée',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $otherAccount->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $sourceId = $source->getId();
        $matchId = $match->getId();
        self::assertNotNull($sourceId);
        self::assertNotNull($matchId);

        $client->request('GET', '/sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Recherche privée autre compte');

        $client->request('GET', '/jobs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Offre privée autre compte');

        $client->request('POST', '/sources/'.$sourceId.'/sync');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/jobs/'.$matchId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testJobOffersDisplayApplicationStatusAndFilterTabs(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — PHP — Lyon',
            'https://example.test/status/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'status-'.$uniqueId,
            url: 'https://example.test/status/'.$uniqueId.'/offer',
            title: 'Offre avec statut '.$uniqueId,
            company: 'Entreprise Status',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $match->updateApplicationStatus(JobApplicationStatus::INTERESTED, 'Offre très intéressante pour mon profil');
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.filter-dropdown');
        self::assertSelectorExists('input[data-offer-filter-target="statusCheckbox"][value="INTERESTED"]');
        self::assertSelectorExists('input[data-offer-filter-target="statusCheckbox"][value="NOT_INTERESTED"]');
        self::assertSelectorExists('input[data-offer-filter-target="statusCheckbox"][value="APPLIED"]');
        self::assertSelectorExists('input[data-offer-filter-target="excludeNotInterested"]');
        self::assertSelectorExists('input[data-offer-filter-target="excludeEsn"]');
        self::assertSelectorExists('.offer-card[data-offer-filter-status="INTERESTED"]');
        self::assertSelectorTextContains('.offer-card[data-offer-filter-status="INTERESTED"] .offer-status-badge', 'M’intéresse');
        self::assertSelectorTextContains('.offer-card[data-offer-filter-status="INTERESTED"] .offer-meta', 'Offre très intéressante');

        $matchId = $match->getId();
        self::assertNotNull($matchId);
        $client->request('GET', '/jobs/'.$matchId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#suivi-candidature');
        self::assertSelectorTextContains('#suivi-candidature', 'M’intéresse');
        self::assertSelectorTextContains('#suivi-candidature blockquote', 'Offre très intéressante pour mon profil');
        self::assertSelectorExists('form[action="/jobs/'.$matchId.'/status"]');
    }

    public function testJobOfferApplicationStatusCanBeUpdated(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — PHP — Lyon',
            'https://example.test/update-status/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'update-status-'.$uniqueId,
            url: 'https://example.test/update-status/'.$uniqueId.'/offer',
            title: 'Offre pour mise à jour statut '.$uniqueId,
            company: 'Entreprise Maj',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $matchId = $match->getId();
        self::assertNotNull($matchId);

        $crawler = $client->request('GET', '/jobs/'.$matchId);
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form.status-edit-form')->form([
            'job_application_status[status]' => JobApplicationStatus::APPLIED->value,
            'job_application_status[reason]' => 'Candidature envoyée ce matin',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/jobs/'.$matchId);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#suivi-candidature', 'Candidaté');
        self::assertSelectorTextContains('#suivi-candidature blockquote', 'Candidature envoyée ce matin');

        // Now test setting NOT_INTERESTED redirects back to /jobs
        $crawler2 = $client->request('GET', '/jobs/'.$matchId);
        $form2 = $crawler2->filter('form.status-edit-form')->form([
            'job_application_status[status]' => JobApplicationStatus::NOT_INTERESTED->value,
            'job_application_status[reason]' => 'Salaire inférieur au marché',
        ]);
        $client->submit($form2);

        self::assertResponseRedirects('/jobs');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $entityManager->clear();
        $reloaded = $entityManager->find(JobMatch::class, $matchId);
        self::assertInstanceOf(JobMatch::class, $reloaded);
        self::assertSame(JobApplicationStatus::NOT_INTERESTED, $reloaded->getApplicationStatus());
        self::assertSame('Salaire inférieur au marché', $reloaded->getStatusReason());
        self::assertNotNull($reloaded->getStatusUpdatedAt());
    }

    public function testAnotherAccountCannotUpdateJobOfferApplicationStatus(): void
    {
        $client = self::createClient();
        $owner = $this->owner();
        $otherAccount = $this->account('other-user-status@example.test');
        $client->loginUser($owner);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(6));

        $source = new JobSource(
            $otherAccount->getCandidateProfile(),
            'Recherche privée',
            'https://example.test/private-status/'.$uniqueId,
            JobProviderType::FAKE,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'priv-status-'.$uniqueId,
            url: 'https://example.test/private-status/'.$uniqueId.'/offer',
            title: 'Offre privée',
            company: 'Entreprise privée',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $otherAccount->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $matchId = $match->getId();
        self::assertNotNull($matchId);

        $client->request('POST', '/jobs/'.$matchId.'/status', [
            'job_application_status' => [
                'status' => JobApplicationStatus::APPLIED->value,
                'reason' => 'Tentative',
            ],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testJobOfferDisplaysJobSummaryAndCapacitiesOnListAndDetailPage(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — Summary Test — '.$uniqueId,
            'https://example.test/summary-test/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'summary-test-'.$uniqueId,
            url: 'https://example.test/summary-test/'.$uniqueId.'/offer',
            title: 'Lead Développeur PHP '.$uniqueId,
            company: 'TechCorp '.$uniqueId,
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 55000,
            maximumSalary: 65000,
            remotePolicy: null,
            yearsOfExperience: 6,
            description: 'Conception et développement de la plateforme SaaS en PHP/Symfony.',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(85, 85, 85, 85, 85, 85, 85, 85, 85, [], [], [], []),
        );
        $match->completeSemanticAnalysis(SemanticJobAnalysis::fromArray([
            'compatibilityScore' => 88,
            'summary' => 'Excellente correspondance technique.',
            'jobSummary' => 'Pilotage de l’architecture SaaS et encadrement technique de l’équipe backend.',
            'keyExpectations' => [
                'Concevoir l’architecture des microservices PHP',
                'Accompagner la montée en compétences des développeurs',
            ],
            'requiredCapacities' => [
                'PHP 8.3 & Symfony 7',
                'Architecture microservices',
                'PostgreSQL',
                'Leadership technique',
            ],
            'requirements' => [
                [
                    'category' => 'TECHNICAL',
                    'importance' => 'REQUIRED',
                    'label' => 'Symfony',
                    'offerEvidence' => 'Symfony 7 requis',
                    'assessment' => 'MATCH',
                    'cvEvidence' => 'Symfony 5 ans',
                    'explanation' => 'Maîtrise validée',
                ],
            ],
            'strengths' => ['Expérience Symfony solide.'],
            'concerns' => [],
            'questions' => [],
        ]), 'test-analyzer');

        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $matchId = $match->getId();
        self::assertNotNull($matchId);

        // Check list view
        $client->request('GET', '/jobs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-snippet-text', 'Pilotage de l’architecture SaaS');
        self::assertSelectorTextContains('.offer-snippet-badges', 'PHP 8.3 & Symfony 7');
        self::assertSelectorTextContains('.offer-snippet-badges', 'Architecture microservices');

        // Check detail view
        $client->request('GET', '/jobs/'.$matchId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#apercu-poste');
        self::assertSelectorTextContains('.job-overview-lead', 'Pilotage de l’architecture SaaS et encadrement technique');
        self::assertSelectorTextContains('#apercu-poste', 'Attentes & Missions clés');
        self::assertSelectorTextContains('#apercu-poste', 'Concevoir l’architecture des microservices PHP');
        self::assertSelectorTextContains('#apercu-poste', 'Capacités & Compétences attendues');
        self::assertSelectorTextContains('.job-capacity-badge', 'PHP 8.3 & Symfony 7');
    }

    public function testQuickStatusCanFavoriteAndDiscardJobOfferViaJson(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — QuickStatus — '.$uniqueId,
            'https://example.test/quick-status/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'qs-'.$uniqueId,
            url: 'https://example.test/qs/'.$uniqueId.'/offer',
            title: 'Offre Quick Status '.$uniqueId,
            company: 'Quick Company',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(70, 70, 70, 70, 70, 70, 70, 70, 70, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $matchId = $match->getId();
        self::assertNotNull($matchId);

        // Get the list page to retrieve CSRF token and verify buttons
        $crawler = $client->request('GET', '/jobs');
        self::assertResponseIsSuccessful();
        $cardActions = $crawler->filter('#job-match-'.$matchId.' .offer-card-actions');
        self::assertCount(1, $cardActions);
        $csrfToken = $cardActions->attr('data-quick-status-token-value');
        self::assertNotEmpty($csrfToken);

        // 1. Mark as FAVORITE / INTERESTED via JSON
        $client->request('POST', '/jobs/'.$matchId.'/quick-status', [
            '_token' => $csrfToken,
            'status' => JobApplicationStatus::INTERESTED->value,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response['success']);
        self::assertSame('INTERESTED', $response['status']);
        self::assertSame('M’intéresse', $response['label']);
        self::assertSame('⭐', $response['icon']);

        $entityManager->clear();
        $reloaded = $entityManager->find(JobMatch::class, $matchId);
        self::assertInstanceOf(JobMatch::class, $reloaded);
        self::assertSame(JobApplicationStatus::INTERESTED, $reloaded->getApplicationStatus());

        // 2. Mark as NOT_INTERESTED via JSON
        $client->request('POST', '/jobs/'.$matchId.'/quick-status', [
            '_token' => $csrfToken,
            'status' => JobApplicationStatus::NOT_INTERESTED->value,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $response2 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response2['success']);
        self::assertSame('NOT_INTERESTED', $response2['status']);
        self::assertSame('Ne m’intéresse pas', $response2['label']);
        self::assertSame('🚫', $response2['icon']);

        $entityManager->clear();
        $reloaded2 = $entityManager->find(JobMatch::class, $matchId);
        self::assertInstanceOf(JobMatch::class, $reloaded2);
        self::assertSame(JobApplicationStatus::NOT_INTERESTED, $reloaded2->getApplicationStatus());

        // 3. Reset back to UNPROCESSED via JSON
        $client->request('POST', '/jobs/'.$matchId.'/quick-status', [
            '_token' => $csrfToken,
            'status' => JobApplicationStatus::UNPROCESSED->value,
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $response3 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response3['success']);
        self::assertSame('UNPROCESSED', $response3['status']);

        $entityManager->clear();
        $reloaded3 = $entityManager->find(JobMatch::class, $matchId);
        self::assertInstanceOf(JobMatch::class, $reloaded3);
        self::assertSame(JobApplicationStatus::UNPROCESSED, $reloaded3->getApplicationStatus());
    }

    public function testAnotherAccountCannotQuickStatusJobOffer(): void
    {
        $client = self::createClient();
        $owner = $this->owner();
        $otherAccount = $this->account('other-qs@example.test');
        $client->loginUser($owner);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(5));

        $source = new JobSource(
            $otherAccount->getCandidateProfile(),
            'Source Autre Compte QS '.$uniqueId,
            'https://example.test/other-qs/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'other-qs-'.$uniqueId,
            url: 'https://example.test/other-qs/'.$uniqueId.'/offer',
            title: 'Offre Autre Compte QS',
            company: 'Entreprise Autre',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Description',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $otherAccount->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();
        $matchId = $match->getId();
        self::assertNotNull($matchId);

        $client->request('POST', '/jobs/'.$matchId.'/quick-status', [
            '_token' => 'invalid-token',
            'status' => JobApplicationStatus::INTERESTED->value,
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testPendingAnalysisOffersDisplayPendingBannerAndAreNotHidden(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(5));
        $account = $this->account('pending-analysis-'.$uniqueId.'@example.test');
        $client->loginUser($account);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — Loading — '.$uniqueId,
            'https://example.test/loading-test/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'loading-'.$uniqueId,
            url: 'https://example.test/loading-test/'.$uniqueId.'/offer',
            title: 'Offre en cours d’analyse '.$uniqueId,
            company: 'Tech Company',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre importée en attente d’analyse sémantique',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch(
            $account->getCandidateProfile(),
            $offer,
            new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []),
        );
        $match->queueSemanticAnalysis();

        $entityManager->persist($source);
        $entityManager->persist($offer);
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.analysis-pending-banner');
        self::assertSelectorTextContains('.analysis-pending-banner', 'Analyse IA en cours');
        self::assertSelectorExists('.offer-card[data-offer-filter-analyzing="1"]');
        self::assertSelectorTextContains('.offer-card[data-offer-filter-analyzing="1"] .offer-score', 'Analyse');
    }

    public function testSmartJobSearchSuggestionsAreDisplayedAndCanBeAdded(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('smart-search-'.$uniqueId.'@example.test');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $profile = $account->getCandidateProfile();
        $cv = new CvDocument($profile, 'cv.pdf', 'cv-'.$uniqueId.'.pdf', 'application/pdf', 1000, hash('sha256', 'cv-'.$uniqueId));
        $cv->markAnalyzing('CV Développeur PHP Symfony');
        $cv->markApplied('Dev Full Stack', 'Lyon', 5, ['CDI']);
        $profile->activateCvDocument($cv);
        $entityManager->persist($cv);

        $skillRepo = $entityManager->getRepository(Skill::class);
        $phpSkill = $skillRepo->findOneBy(['normalizedName' => 'php']) ?? new Skill('PHP', 'php', SkillCategory::BACKEND);
        $symfonySkill = $skillRepo->findOneBy(['normalizedName' => 'symfony']) ?? new Skill('Symfony', 'symfony', SkillCategory::BACKEND);
        if ($phpSkill->getId() === null) {
            $entityManager->persist($phpSkill);
        }
        if ($symfonySkill->getId() === null) {
            $entityManager->persist($symfonySkill);
        }

        $candSkill1 = new CandidateSkill($profile, $phpSkill, SkillLevel::EXPERT, isCoreSkill: true, cvDocument: $cv);
        $candSkill2 = new CandidateSkill($profile, $symfonySkill, SkillLevel::ADVANCED, isCoreSkill: true, cvDocument: $cv);
        $entityManager->persist($candSkill1);
        $entityManager->persist($candSkill2);
        $entityManager->flush();

        $client->loginUser($account);

        $crawler = $client->request('GET', '/sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.smart-searches-box');
        self::assertSelectorTextContains('.smart-searches-list', 'Dev Full Stack PHP');
        self::assertSelectorTextContains('.smart-searches-list', 'Développeur Symfony');
        self::assertSelectorExists('.smart-search-chip.selectable');

        // Select and submit multiple suggested searches at once
        $token = (string) $crawler->filter('.smart-searches-form input[name="_token"]')->attr('value');
        $client->request('POST', '/sources/add-multiple', [
            '_token' => $token,
            'titles' => ['Dev Full Stack PHP', 'Développeur Symfony'],
        ]);

        self::assertResponseRedirects('/sources');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash-success', '2 recherches ont été ajoutées et mises en attente d’import.');
    }

    public function testExpiredJobOffersAreExcludedFromJobListings(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('expired-offers-'.$uniqueId.'@example.test');
        $client->loginUser($account);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $source = new JobSource(
            $account->getCandidateProfile(),
            'HelloWork — Expired Test — '.$uniqueId,
            'https://example.test/expired-test/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $entityManager->persist($source);

        // 1. Active offer
        $activeOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'active-'.$uniqueId,
            url: 'https://example.test/active-'.$uniqueId,
            title: 'Offre Active '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre toujours active',
            publishedAt: new \DateTimeImmutable('yesterday'),
            validThrough: new \DateTimeImmutable('+30 days'),
            rawPayload: [],
        ));
        $activeMatch = new JobMatch(
            $account->getCandidateProfile(),
            $activeOffer,
            new MatchScore(85, 85, 85, 85, 85, 85, 85, 85, 85, [], [], [], []),
        );
        $entityManager->persist($activeOffer);
        $entityManager->persist($activeMatch);

        // 2. Expired by status
        $expiredStatusOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'expired-status-'.$uniqueId,
            url: 'https://example.test/expired-status-'.$uniqueId,
            title: 'Offre Expirée par Statut '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre au statut expiré',
            publishedAt: new \DateTimeImmutable('-10 days'),
            validThrough: null,
            rawPayload: [],
        ));
        $expiredStatusOffer->markExpired();
        $expiredStatusMatch = new JobMatch(
            $account->getCandidateProfile(),
            $expiredStatusOffer,
            new MatchScore(90, 90, 90, 90, 90, 90, 90, 90, 90, [], [], [], []),
        );
        $entityManager->persist($expiredStatusOffer);
        $entityManager->persist($expiredStatusMatch);

        // 3. Expired by date (validThrough in the past)
        $expiredDateOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'expired-date-'.$uniqueId,
            url: 'https://example.test/expired-date-'.$uniqueId,
            title: 'Offre Expirée par Date '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre avec date de fin dépassée',
            publishedAt: new \DateTimeImmutable('-20 days'),
            validThrough: new \DateTimeImmutable('-2 days'),
            rawPayload: [],
        ));
        $expiredDateMatch = new JobMatch(
            $account->getCandidateProfile(),
            $expiredDateOffer,
            new MatchScore(95, 95, 95, 95, 95, 95, 95, 95, 95, [], [], [], []),
        );
        $entityManager->persist($expiredDateOffer);
        $entityManager->persist($expiredDateMatch);
        $entityManager->flush();

        // Check ranked view
        $client->request('GET', '/jobs?view=ranked');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-list', 'Offre Active '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Expirée par Statut '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Expirée par Date '.$uniqueId);

        // Check latest view
        $client->request('GET', '/jobs?view=latest');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-list', 'Offre Active '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Expirée par Statut '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Expirée par Date '.$uniqueId);
    }

    public function testOffersAreFilteredByCandidatePreferredContractTypes(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('contract-filter-'.$uniqueId.'@example.test');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        $profile = $account->getCandidateProfile();
        $profile->updateDetails('Développeur Backend', 'Lyon', 5, ['CDI']);

        $source = new JobSource(
            $profile,
            'HelloWork — Contract Test — '.$uniqueId,
            'https://example.test/contract-test/'.$uniqueId,
            JobProviderType::HELLOWORK,
        );
        $entityManager->persist($source);

        // 1. CDI offer (compatible)
        $cdiOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'cdi-'.$uniqueId,
            url: 'https://example.test/cdi-'.$uniqueId,
            title: 'Offre CDI '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre en CDI',
            publishedAt: new \DateTimeImmutable('yesterday'),
            validThrough: new \DateTimeImmutable('+30 days'),
            rawPayload: [],
        ));
        $cdiMatch = new JobMatch(
            $profile,
            $cdiOffer,
            new MatchScore(90, 90, 90, 90, 90, 90, 90, 90, 90, [], [], [], []),
        );
        $entityManager->persist($cdiOffer);
        $entityManager->persist($cdiMatch);

        // 2. Freelance offer (incompatible with CDI-only preference)
        $freelanceOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'freelance-'.$uniqueId,
            url: 'https://example.test/freelance-'.$uniqueId,
            title: 'Offre Freelance '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'Freelance',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre en Freelance',
            publishedAt: new \DateTimeImmutable('yesterday'),
            validThrough: new \DateTimeImmutable('+30 days'),
            rawPayload: [],
        ));
        $freelanceMatch = new JobMatch(
            $profile,
            $freelanceOffer,
            new MatchScore(95, 95, 95, 95, 95, 95, 95, 95, 95, [], [], [], []),
        );
        $entityManager->persist($freelanceOffer);
        $entityManager->persist($freelanceMatch);

        // 3. Stage offer (incompatible with CDI-only preference)
        $stageOffer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'stage-'.$uniqueId,
            url: 'https://example.test/stage-'.$uniqueId,
            title: 'Offre Stage '.$uniqueId,
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'Stage',
            minimumSalary: null,
            maximumSalary: null,
            remotePolicy: null,
            yearsOfExperience: null,
            description: 'Offre en Stage',
            publishedAt: new \DateTimeImmutable('yesterday'),
            validThrough: new \DateTimeImmutable('+30 days'),
            rawPayload: [],
        ));
        $stageMatch = new JobMatch(
            $profile,
            $stageOffer,
            new MatchScore(80, 80, 80, 80, 80, 80, 80, 80, 80, [], [], [], []),
        );
        $entityManager->persist($stageOffer);
        $entityManager->persist($stageMatch);
        $entityManager->flush();

        $client->loginUser($account);

        // Check ranked view: only CDI should appear
        $client->request('GET', '/jobs?view=ranked');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-list', 'Offre CDI '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Freelance '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Stage '.$uniqueId);

        // Check latest view: only CDI should appear
        $client->request('GET', '/jobs?view=latest');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.offer-list', 'Offre CDI '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Freelance '.$uniqueId);
        self::assertSelectorTextNotContains('.offer-list', 'Offre Stage '.$uniqueId);

        // Check daily alerts query: only CDI should be returned
        $matchRepo = self::getContainer()->get(JobMatchRepository::class);
        self::assertInstanceOf(JobMatchRepository::class, $matchRepo);
        $alertMatches = $matchRepo->findMatchesForDailyAlert($profile, 70, new \DateTimeImmutable('-2 days'), 10, true);
        $alertTitles = array_map(static fn (JobMatch $m): string => $m->getJobOffer()->getTitle(), $alertMatches);
        self::assertContains('Offre CDI '.$uniqueId, $alertTitles);
        self::assertNotContains('Offre Freelance '.$uniqueId, $alertTitles);
        self::assertNotContains('Offre Stage '.$uniqueId, $alertTitles);
    }
}
