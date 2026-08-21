<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use App\Candidate\Infrastructure\Persistence\CvDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CvPagesControllerTest extends WebTestCase
{
    public function testCvUploadPageUsesTheCustomDropZone(): void
    {
        $client = self::createClient();
        $client->request('GET', '/cv');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.upload-zone', 'Déposez votre CV ici');
        self::assertSelectorExists('input[type="file"].upload-input');
    }

    public function testProfileShowsTheCvOptimizationArea(): void
    {
        $client = self::createClient();
        $client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.optimization-section h2', 'Optimiser mon CV');
        self::assertSelectorTextContains('.optimization-section', 'n’ajoutent jamais une compétence sans preuve');
    }

    public function testACompletedCvCanBeDeletedWithTurbo(): void
    {
        $client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $profileRepository = self::getContainer()->get(CandidateProfileRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(CandidateProfileRepository::class, $profileRepository);

        $profile = $profileRepository->findDefault() ?? new CandidateProfile();
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
        $entityManager->persist($profile);
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
        self::assertNull($profileRepository->findDefault()?->getRawCvText());
    }
}
