<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Application\Storage\CvStorageInterface;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use App\Candidate\Infrastructure\Persistence\CvDocumentRepository;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use Doctrine\ORM\EntityManagerInterface;

final class CvPagesControllerTest extends AuthenticatedWebTestCase
{
    public function testCvUploadPageUsesTheCustomDropZone(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $client->request('GET', '/cv');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.upload-zone', 'Déposez votre CV ici');
        self::assertSelectorExists('input[type="file"].upload-input');
        self::assertSelectorExists('.upload-card');
    }

    public function testCvOptimizationHasItsOwnPage(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.optimization-section');
        self::assertSelectorExists('nav a[href="/profile/optimisation-cv"]');

        $client->request('GET', '/profile/optimisation-cv');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Optimiser mon CV');
        self::assertSelectorTextContains('.page-heading', 'n’ajoutent jamais une compétence sans preuve');
    }

    public function testACompletedCvCanBeDeletedWithTurbo(): void
    {
        $client = self::createClient();
        $account = $this->loginOwner($client);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $profileRepository = self::getContainer()->get(CandidateProfileRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(CandidateProfileRepository::class, $profileRepository);

        $profile = $account->getCandidateProfile();
        $profileId = $profile->getId();
        self::assertNotNull($profileId);
        $document = new CvDocument(
            $profile,
            'cv-a-supprimer.pdf',
            'functional-delete-cv.pdf',
            'application/pdf',
            1024,
            hash('sha256', 'functional-delete-cv'),
        );
        $document->markAnalyzing('Contenu privé du CV à supprimer');
        $document->fail('Échec de test');
        $profile->updateFromCv('Développeur', 'Paris', 3, 'Contenu privé du CV à supprimer');
        $entityManager->persist($document);
        $entityManager->flush();
        $documentId = $document->getId();
        self::assertNotNull($documentId);

        $crawler = $client->request('GET', '/cv');
        $form = $crawler->filter(sprintf('form[action="/cv/%d/delete"]', $documentId))->form();
        $client->submit($form, [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $responseContent = $client->getResponse()->getContent();
        self::assertIsString($responseContent);
        self::assertStringContainsString(sprintf('target="cv-document-%d"', $documentId), $responseContent);

        $entityManager->clear();
        $documentRepository = self::getContainer()->get(CvDocumentRepository::class);
        self::assertInstanceOf(CvDocumentRepository::class, $documentRepository);
        self::assertNull($documentRepository->find($documentId));
        self::assertNull($profileRepository->find($profileId)?->getRawCvText());
    }

    public function testAnotherAccountsCvIsNeitherListedNorAccessible(): void
    {
        $client = self::createClient();
        $owner = $this->owner();
        $otherAccount = $this->account('other-cv@example.test');
        $client->loginUser($owner);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $uniqueId = bin2hex(random_bytes(6));

        $document = new CvDocument(
            $otherAccount->getCandidateProfile(),
            'cv-prive-autre-compte.pdf',
            'functional-private-other-account-'.$uniqueId.'.pdf',
            'application/pdf',
            1024,
            hash('sha256', 'functional-private-other-account-'.$uniqueId),
        );
        $entityManager->persist($document);
        $entityManager->flush();
        $documentId = $document->getId();
        self::assertNotNull($documentId);

        $client->request('GET', '/cv');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'cv-prive-autre-compte.pdf');

        $client->request('GET', '/cv/'.$documentId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testActivatingAnotherCvSwitchesItsSkillsAndOffers(): void
    {
        $client = self::createClient();
        $account = $this->account('cv-switch-'.bin2hex(random_bytes(6)).'@example.test');
        $client->loginUser($account);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $profile = $account->getCandidateProfile();
        $uniqueId = bin2hex(random_bytes(6));

        $phpCv = $this->appliedDocument($profile, 'cv-php-'.$uniqueId.'.pdf', 'Développeur PHP');
        $reactCv = $this->appliedDocument($profile, 'cv-react-'.$uniqueId.'.pdf', 'Développeur React');
        $phpSkill = new Skill('PHP '.$uniqueId, 'php-'.$uniqueId, SkillCategory::BACKEND);
        $reactSkill = new Skill('React '.$uniqueId, 'react-'.$uniqueId, SkillCategory::FRONTEND);
        new CandidateSkill($profile, $phpSkill, cvDocument: $phpCv);
        new CandidateSkill($profile, $reactSkill, cvDocument: $reactCv);
        $phpSource = new JobSource($profile, 'Recherche PHP '.$uniqueId, 'https://example.test/php-'.$uniqueId, JobProviderType::FAKE, $phpCv);
        $reactSource = new JobSource($profile, 'Recherche React '.$uniqueId, 'https://example.test/react-'.$uniqueId, JobProviderType::FAKE, $reactCv);
        $phpMatch = $this->match($profile, $phpSource, 'Offre PHP '.$uniqueId, 'php-'.$uniqueId);
        $reactMatch = $this->match($profile, $reactSource, 'Offre React '.$uniqueId, 'react-'.$uniqueId);
        $profile->activateCvDocument($phpCv);

        foreach ([
            $phpCv, $reactCv,
            $phpSkill, $reactSkill,
            $phpSource, $reactSource,
            $phpMatch->getJobOffer(), $reactMatch->getJobOffer(),
            $phpMatch, $reactMatch,
        ] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $client->request('GET', '/profile');
        self::assertSelectorTextContains('body', 'PHP '.$uniqueId);
        self::assertSelectorTextNotContains('body', 'React '.$uniqueId);
        $client->request('GET', '/jobs');
        self::assertSelectorTextContains('body', 'Offre PHP '.$uniqueId);
        self::assertSelectorTextNotContains('body', 'Offre React '.$uniqueId);

        $crawler = $client->request('GET', '/cv');
        $activationForm = $crawler->filter(sprintf('form[action="/cv/%d/activate"]', $reactCv->getId()))->form();
        $client->submit($activationForm);
        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'React '.$uniqueId);
        self::assertSelectorTextNotContains('body', 'PHP '.$uniqueId);

        $client->request('GET', '/jobs');
        self::assertSelectorTextContains('body', 'Offre React '.$uniqueId);
        self::assertSelectorTextNotContains('body', 'Offre PHP '.$uniqueId);

        $client->request('GET', '/jobs/'.$phpMatch->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerCanViewOriginalCvFileAndExtractedText(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('cv-view-'.$uniqueId.'@example.test');
        $client->loginUser($account);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $storage = self::getContainer()->get(CvStorageInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(CvStorageInterface::class, $storage);

        $profile = $account->getCandidateProfile();
        $storedFilename = 'functional-view-'.$uniqueId.'.pdf';
        $filePath = $storage->absolutePath($storedFilename);
        @mkdir(dirname($filePath), 0700, true);
        file_put_contents($filePath, '%PDF-1.4 Fake PDF Content for Functional Test');

        $document = new CvDocument(
            $profile,
            'mon-beau-cv.pdf',
            $storedFilename,
            'application/pdf',
            2048,
            hash('sha256', 'fake-pdf-'.$uniqueId),
        );
        $document->markAnalyzing("Développeur PHP Symfony avec 6 ans d'expérience.");
        $document->markApplied('Développeur PHP', 'Lyon', 6);
        $profile->activateCvDocument($document);
        $entityManager->persist($document);
        $entityManager->flush();

        $documentId = $document->getId();
        self::assertNotNull($documentId);

        // 1. Direct file download/stream
        $client->request('GET', '/cv/'.$documentId.'/file');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertResponseHeaderSame('x-content-type-options', 'nosniff');
        $disposition = $client->getResponse()->headers->get('content-disposition');
        self::assertIsString($disposition);
        self::assertStringContainsString('inline', $disposition);
        self::assertStringContainsString('mon-beau-cv.pdf', $disposition);

        // 2. CV Show page contains viewer & extracted text
        $client->request('GET', '/cv/'.$documentId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/cv/'.$documentId.'/file"]');
        self::assertSelectorExists('iframe[src="/cv/'.$documentId.'/file"]');
        self::assertSelectorExists('.cv-text-disclosure');
        self::assertSelectorTextContains('.cv-raw-text-block', 'Développeur PHP Symfony avec 6 ans');

        // 3. Profile page has banner with direct view link
        $client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.profile-active-cv-banner a[href="/cv/'.$documentId.'/file"]');

        // 4. CV Upload page list has view link
        $client->request('GET', '/cv');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.document-row a[href="/cv/'.$documentId.'/file"]');

        // Clean up temp file
        @unlink($filePath);
    }

    public function testAnotherAccountCannotAccessCvFile(): void
    {
        $client = self::createClient();
        $owner = $this->owner();
        $otherAccount = $this->account('other-cv-file@example.test');
        $client->loginUser($owner);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $storage = self::getContainer()->get(CvStorageInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(CvStorageInterface::class, $storage);

        $uniqueId = bin2hex(random_bytes(6));
        $storedFilename = 'functional-private-file-'.$uniqueId.'.pdf';
        $filePath = $storage->absolutePath($storedFilename);
        @mkdir(dirname($filePath), 0700, true);
        file_put_contents($filePath, '%PDF-1.4 Private CV');

        $document = new CvDocument(
            $otherAccount->getCandidateProfile(),
            'cv-secret.pdf',
            $storedFilename,
            'application/pdf',
            1024,
            hash('sha256', 'secret-'.$uniqueId),
        );
        $entityManager->persist($document);
        $entityManager->flush();
        $documentId = $document->getId();
        self::assertNotNull($documentId);

        $client->request('GET', '/cv/'.$documentId.'/file');
        self::assertResponseStatusCodeSame(404);

        @unlink($filePath);
    }

    private function appliedDocument(\App\Candidate\Entity\CandidateProfile $profile, string $filename, string $title): CvDocument
    {
        $document = new CvDocument($profile, $filename, 'stored-'.$filename, 'application/pdf', 1024, hash('sha256', $filename));
        $document->markAnalyzing('Contenu de '.$filename);
        $document->markApplied($title, 'Paris', 5);

        return $document;
    }

    private function match(\App\Candidate\Entity\CandidateProfile $profile, JobSource $source, string $title, string $externalId): JobMatch
    {
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: $externalId,
            url: 'https://example.test/offers/'.$externalId,
            title: $title,
            company: 'Entreprise test',
            location: 'Paris',
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

        return new JobMatch($profile, $offer, new MatchScore(50, 50, 50, 50, 50, 50, 50, 50, 50, [], [], [], []));
    }
}
