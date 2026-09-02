<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CvDocument;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Repository\JobMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JobApplicationControllerTest extends AuthenticatedWebTestCase
{
    public function testKanbanBoardPageIsAvailableAndDisplaysColumns(): void
    {
        $client = self::createClient();
        $account = $this->account('kanban-user-1@example.test');
        $client->loginUser($account);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $profile = $account->getCandidateProfile();
        $uniqueId = bin2hex(random_bytes(6));
        $cv = new CvDocument($profile, 'cv.pdf', 'stored-'.$uniqueId.'.pdf', 'application/pdf', 100, hash('sha256', 'kanban-cv-'.$uniqueId));
        $cv->markAnalyzing('Contenu du CV Développeur PHP');
        $cv->markApplied('Développeur PHP', 'Lyon', 5, ['CDI']);
        $profile->activateCvDocument($cv);
        $entityManager->persist($cv);

        $source = new JobSource($profile, 'Apec — PHP — Lyon', 'https://example.test/apec-php-'.$uniqueId, JobProviderType::APEC, $cv);
        $entityManager->persist($source);

        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'kanban-offer-'.$uniqueId,
            url: 'https://example.test/kanban-'.$uniqueId,
            title: 'Lead Dev PHP',
            company: 'Tech Corp',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 55000,
            maximumSalary: 65000,
            remotePolicy: 'HYBRID',
            yearsOfExperience: 5,
            description: 'Mission PHP Symfony',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $entityManager->persist($offer);

        $match = new JobMatch($profile, $offer, new MatchScore(90, 85, 90, 90, 90, 90, 90, 90, 90, [], [], [], []));
        $match->updateApplicationStatus(JobApplicationStatus::APPLIED, 'Candidature envoyée le 02/09');
        $entityManager->persist($match);
        $entityManager->flush();

        $client->request('GET', '/applications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Suivi des candidatures');
        self::assertSelectorTextContains('.kanban-column-applied', 'Lead Dev PHP');
        self::assertSelectorTextContains('.kanban-column-applied', 'Tech Corp');
        self::assertSelectorTextContains('.kanban-card-note-text', 'Candidature envoyée le 02/09');
    }

    public function testJobApplicationNoteCanBeUpdatedViaAjax(): void
    {
        $client = self::createClient();
        $account = $this->account('kanban-user-note@example.test');
        $client->loginUser($account);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $profile = $account->getCandidateProfile();
        $uniqueId = bin2hex(random_bytes(6));
        $cv = new CvDocument($profile, 'cv.pdf', 'stored-'.$uniqueId.'.pdf', 'application/pdf', 100, hash('sha256', 'kanban-cv-note-'.$uniqueId));
        $cv->markAnalyzing('Contenu du CV Développeur PHP');
        $cv->markApplied('Développeur PHP', 'Lyon', 5, ['CDI']);
        $profile->activateCvDocument($cv);
        $entityManager->persist($cv);

        $source = new JobSource($profile, 'Apec — PHP — Lyon', 'https://example.test/apec-php-note', JobProviderType::APEC, $cv);
        $entityManager->persist($source);

        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'kanban-offer-note',
            url: 'https://example.test/kanban-note',
            title: 'Développeur Symfony',
            company: 'Agency XYZ',
            location: 'Lyon',
            contractType: 'CDI',
            minimumSalary: 45000,
            maximumSalary: 50000,
            remotePolicy: 'HYBRID',
            yearsOfExperience: 3,
            description: 'Poste Symfony',
            publishedAt: new \DateTimeImmutable(),
            validThrough: null,
            rawPayload: [],
        ));
        $entityManager->persist($offer);

        $match = new JobMatch($profile, $offer, new MatchScore(85, 80, 85, 85, 85, 85, 85, 85, 85, [], [], [], []));
        $match->updateApplicationStatus(JobApplicationStatus::INTERVIEW);
        $entityManager->persist($match);
        $entityManager->flush();

        $crawler = $client->request('GET', '/applications');
        $token = (string) $crawler->filter(sprintf('form[action="/applications/%d/note"] input[name="_token"]', $match->getId()))->attr('value');

        $client->request('POST', sprintf('/applications/%d/note', $match->getId()), [
            '_token' => $token,
            'note' => 'Entretien technique prévu le 05/09 à 14h',
        ], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($json['success']);
        self::assertSame('Entretien technique prévu le 05/09 à 14h', $json['note']);

        /** @var JobMatchRepository $matchRepository */
        $matchRepository = self::getContainer()->get(JobMatchRepository::class);
        $reloaded = $matchRepository->get((int) $match->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Entretien technique prévu le 05/09 à 14h', $reloaded->getStatusReason());
    }
}
