<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\JobSearchType;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Job\DTO\JobSearchData;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
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
        $sourceGroups = $this->groupSourcesBySearch($sources);
        $suggestedSearches = $searchService->getSmartQueries($profile);

        return $this->render('job/source/index.html.twig', [
            'sources' => $sources,
            'sourceGroups' => $sourceGroups,
            'hasMissingFreelanceSources' => array_any($sourceGroups, static fn (array $group): bool => !$group['hasFreelance']),
            'hasActiveSync' => array_any($sources, static fn (JobSource $source): bool => $source->isSyncPending()),
            'searchForm' => $form,
            'profileLocation' => $profile->getLocation(),
            'suggestedSearches' => $suggestedSearches,
        ]);
    }

    #[Route('/sources/add-freelance', name: 'app_job_sources_add_freelance', methods: ['POST'])]
    public function addFreelanceSources(
        Request $request,
        #[CurrentUser] Account $account,
        JobSourceRepository $repository,
        ConfigureCandidateJobSearchService $searchService,
    ): Response {
        if (!$this->isCsrfTokenValid('add-freelance-sources', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $profile = $account->getCandidateProfile();
        $location = $profile->getLocation();
        if ($location === null) {
            $this->addFlash('error', JobMessage::SEARCH_CRITERIA_REQUIRED);

            return $this->redirectToRoute('app_job_sources');
        }

        $addedCount = 0;
        foreach ($this->groupSourcesBySearch($repository->findForProfile($profile)) as $group) {
            if ($group['hasFreelance']) {
                continue;
            }

            $searchService->configureProviderSource($profile, $group['label'], $location, JobProviderType::FREE_WORK);
            ++$addedCount;
        }

        $this->addFlash('success', $addedCount > 0
            ? sprintf('Free-Work a été ajouté à %d intitulé%s de recherche.', $addedCount, $addedCount > 1 ? 's' : '')
            : 'Free-Work est déjà présent pour tous les intitulés.');

        return $this->redirectToRoute('app_job_sources');
    }

    #[Route('/sources/delete-search', name: 'app_job_sources_delete_search', methods: ['POST'])]
    public function deleteSearch(
        Request $request,
        #[CurrentUser] Account $account,
        JobSourceRepository $repository,
        EntityManagerInterface $entityManager,
    ): Response {
        $searchLabel = trim((string) $request->request->get('search_label'));
        if (!$this->isCsrfTokenValid('delete-search-'.$searchLabel, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $sources = array_values(array_filter(
            $repository->findForProfile($account->getCandidateProfile()),
            static fn (JobSource $source): bool => $source->getSearchLabel() === $searchLabel,
        ));
        if ($searchLabel === '' || $sources === []) {
            throw $this->createNotFoundException(JobMessage::SOURCE_NOT_FOUND);
        }
        if (array_any($sources, static fn (JobSource $source): bool => $source->isSyncPending())) {
            $this->addFlash('error', JobMessage::SOURCE_DELETE_SYNC_PENDING);

            return $this->redirectToRoute('app_job_sources');
        }

        foreach ($sources as $source) {
            $entityManager->remove($source);
        }
        $entityManager->flush();
        $this->addFlash('success', sprintf(
            'L’intitulé « %s » et ses %d source%s ont été supprimés.',
            $searchLabel,
            count($sources),
            count($sources) > 1 ? 's' : '',
        ));

        return $this->redirectToRoute('app_job_sources');
    }

    #[Route('/sources/add-multiple', name: 'app_job_sources_add_multiple', methods: ['POST'])]
    public function addMultiple(
        Request $request,
        #[CurrentUser] Account $account,
        ConfigureCandidateJobSearchService $searchService,
    ): Response {
        $profile = $account->getCandidateProfile();
        $location = $profile->getLocation();

        if ($location === null) {
            $this->addFlash('error', JobMessage::SEARCH_CRITERIA_REQUIRED);

            return $this->redirectToRoute('app_job_sources');
        }

        if (!$this->isCsrfTokenValid('add-multiple-sources', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $titles = (array) $request->request->all('titles');
        $validTitles = array_values(array_unique(array_filter(
            $titles,
            static fn (mixed $title): bool => is_string($title) && trim($title) !== '',
        )));

        if ($validTitles === []) {
            $this->addFlash('error', 'Veuillez sélectionner au moins un intitulé de recherche.');

            return $this->redirectToRoute('app_job_sources');
        }

        foreach ($validTitles as $title) {
            $searchService->configureTitle($profile, $title, $location);
        }

        $this->addFlash('success', sprintf(
            '%d recherche%s ajoutée%s et mise%s en attente d’import.',
            count($validTitles),
            count($validTitles) > 1 ? 's ont été' : ' a été',
            count($validTitles) > 1 ? 's' : '',
            count($validTitles) > 1 ? 's' : '',
        ));

        return $this->redirectToRoute('app_job_sources');
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
        if ($account->getCandidateProfile()->getId() !== $source->getCandidateProfile()->getId()
            || !$source->belongsToActiveCv()) {
            throw $this->createNotFoundException(JobMessage::SOURCE_NOT_FOUND);
        }
    }

    /**
     * @param list<JobSource> $sources
     *
     * @return array<string, array{label: string, sources: list<JobSource>, syncPending: bool, hasFreelance: bool}>
     */
    private function groupSourcesBySearch(array $sources): array
    {
        $groups = [];
        foreach ($sources as $source) {
            $label = $source->getSearchLabel();
            $groups[$label] ??= [
                'label' => $label,
                'sources' => [],
                'syncPending' => false,
                'hasFreelance' => false,
            ];
            $groups[$label]['sources'][] = $source;
            $groups[$label]['syncPending'] = $groups[$label]['syncPending'] || $source->isSyncPending();
            $groups[$label]['hasFreelance'] = $groups[$label]['hasFreelance'] || $source->getProvider() === JobProviderType::FREE_WORK;
        }

        return $groups;
    }
}
