<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\JobSearchType;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Job\DTO\JobSearchData;
use App\Job\Entity\JobSource;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Repository\JobSourceRepository;
use App\Job\Translation\JobMessage;
use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Turbo\TurboBundle;

final class JobSourceController extends AbstractController
{
    #[Route('/sources', name: 'app_job_sources', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] Account $account,
        JobSourceRepository $repository,
        ConfigureCandidateJobSearchService $searchService,
    ): Response {
        $profile = $account->getCandidateProfile();
        $searchData = new JobSearchData();
        $form = $this->createForm(JobSearchType::class, $searchData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $profile->getLocation() !== null) {
            $searchService->configureTitle($profile, $searchData->title, $profile->getLocation());
            $this->addFlash('success', JobMessage::SEARCH_ADDED);

            return $this->redirectToRoute('app_job_sources');
        }

        $sources = $repository->findForProfile($profile);

        return $this->render('job/source/index.html.twig', [
            'sources' => $sources,
            'hasActiveSync' => array_any($sources, static fn (JobSource $source): bool => $source->isSyncPending()),
            'searchForm' => $form,
            'profileLocation' => $profile->getLocation(),
        ]);
    }

    #[Route('/sources/{id<\d+>}/sync', name: 'app_job_source_sync', methods: ['POST'])]
    public function sync(
        JobSource $source,
        #[CurrentUser] Account $account,
        Request $request,
        MessageBusInterface $messageBus,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertOwnsSource($account, $source);
        if (!$this->isCsrfTokenValid('sync-source-'.$source->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $sourceId = $source->getId();
        if ($sourceId === null) {
            throw new \RuntimeException(JobMessage::SOURCE_NOT_FOUND);
        }

        $source->queueSync();
        $entityManager->flush();
        $messageBus->dispatch(new ImportJobSourceMessage($sourceId));
        $this->addFlash('success', JobMessage::SYNC_DISPATCHED);

        return $this->redirectToRoute('app_job_sources');
    }

    #[Route('/sources/{id<\d+>}/delete', name: 'app_job_source_delete', methods: ['POST'])]
    public function delete(
        JobSource $source,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $this->assertOwnsSource($account, $source);
        if (!$this->isCsrfTokenValid('delete-source-'.$source->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($source->isSyncPending()) {
            $this->addFlash('error', JobMessage::SOURCE_DELETE_SYNC_PENDING);

            return $this->redirectToRoute('app_job_sources');
        }

        $sourceId = $source->getId();
        $entityManager->remove($source);
        $entityManager->flush();
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('job/source/delete.stream.html.twig', [
                'message' => $translator->trans(JobMessage::SOURCE_DELETED),
                'sourceId' => $sourceId,
            ]);
        }
        $this->addFlash('success', JobMessage::SOURCE_DELETED);

        return $this->redirectToRoute('app_job_sources');
    }

    private function assertOwnsSource(Account $account, JobSource $source): void
    {
        if ($account->getCandidateProfile()->getId() !== $source->getCandidateProfile()->getId()) {
            throw $this->createNotFoundException(JobMessage::SOURCE_NOT_FOUND);
        }
    }
}
