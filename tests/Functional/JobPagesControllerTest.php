<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Repository\JobSourceRepository;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
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
        self::assertSelectorTextContains('.page-heading', 'synchronisées automatiquement');
        self::assertSelectorTextContains('.form-card h2', 'Ajouter une recherche');
    }

    public function testJobOfferPageIsAvailable(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Offres analysées');
        self::assertSelectorTextContains('.page-heading', 'compatibilité calculée par l’IA');
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
}
