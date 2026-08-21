<?php

declare(strict_types=1);

namespace App\Controller;

use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use App\Form\JobSearchType;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Job\DTO\JobSearchData;
use App\Job\Entity\JobSource;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Repository\JobSourceRepository;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class JobSourceController extends AbstractController
{
    #[Route('/sources', name: 'app_job_sources', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        JobSourceRepository $repository,
        CandidateProfileRepository $profileRepository,
        ConfigureCandidateJobSearchService $searchService,
    ): Response {
        $profile = $profileRepository->findDefault();
        $searchData = new JobSearchData();
        $form = $this->createForm(JobSearchType::class, $searchData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $profile !== null && $profile->getLocation() !== null) {
            $searchService->configureTitle($searchData->title, $profile->getLocation());
            $this->addFlash('success', JobMessage::SEARCH_ADDED);

            return $this->redirectToRoute('app_job_sources');
        }

        $sources = $repository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('job/source/index.html.twig', [
            'sources' => $sources,
            'hasActiveSync' => array_any($sources, static fn (JobSource $source): bool => $source->isSyncPending()),
            'searchForm' => $form,
            'profileLocation' => $profile?->getLocation(),
        ]);
    }

    #[Route('/sources/{id<\d+>}/sync', name: 'app_job_source_sync', methods: ['POST'])]
    public function sync(JobSource $source, Request $request, MessageBusInterface $messageBus, EntityManagerInterface $entityManager): Response
    {
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
}
