<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\JobApplicationStatusType;
use App\Matching\DTO\JobApplicationStatusData;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Repository\JobMatchRepository;
use App\Matching\Translation\MatchingMessage;
use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class JobOfferController extends AbstractController
{
    #[Route('/jobs', name: 'app_job_offers', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] Account $account, JobMatchRepository $repository): Response
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($request->query->has('view')) {
            $view = $request->query->getString('view');
            if (in_array($view, ['ranked', 'latest'], true)) {
                $session?->set('job_offers_view', $view);
            } else {
                $view = 'ranked';
            }
        } elseif ($session !== null && $session->has('job_offers_view')) {
            $view = (string) $session->get('job_offers_view');
            if (!in_array($view, ['ranked', 'latest'], true)) {
                $view = 'ranked';
            }
        } else {
            $view = 'ranked';
        }

        $profile = $account->getCandidateProfile();
        $matches = $view === 'latest'
            ? $repository->findLatestForProfile($profile)
            : $repository->findRankedForProfile($profile);

        return $this->render('job/offer/index.html.twig', [
            'matches' => $matches,
            'currentView' => $view,
        ]);
    }

    #[Route('/jobs/{id<\d+>}', name: 'app_job_offer_show', methods: ['GET'])]
    public function show(JobMatch $match, #[CurrentUser] Account $account): Response
    {
        $this->assertOwnsMatch($account, $match);
        $semanticAnalysis = $match->getSemanticAnalysis();

        $statusData = new JobApplicationStatusData();
        $statusData->status = $match->getApplicationStatus();
        $statusData->reason = $match->getStatusReason();
        $statusForm = $this->createForm(JobApplicationStatusType::class, $statusData, [
            'action' => $this->generateUrl('app_job_offer_update_status', ['id' => $match->getId()]),
        ]);

        return $this->render('job/offer/show.html.twig', [
            'match' => $match,
            'semanticAnalysis' => $semanticAnalysis === null ? null : SemanticJobAnalysis::fromArray($semanticAnalysis),
            'statusForm' => $statusForm->createView(),
        ]);
    }

    #[Route('/jobs/{id<\d+>}/status', name: 'app_job_offer_update_status', methods: ['POST'])]
    public function updateStatus(
        JobMatch $match,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertOwnsMatch($account, $match);

        $statusData = new JobApplicationStatusData();
        $statusData->status = $match->getApplicationStatus();
        $statusData->reason = $match->getStatusReason();
        $form = $this->createForm(JobApplicationStatusType::class, $statusData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $match->updateApplicationStatus($statusData->status, $statusData->reason);
            $entityManager->flush();
            $this->addFlash('success', MatchingMessage::APPLICATION_STATUS_UPDATED);

            if ($statusData->status === JobApplicationStatus::NOT_INTERESTED) {
                return $this->redirectToRoute('app_job_offers');
            }
        }

        return $this->redirectToRoute('app_job_offer_show', ['id' => $match->getId()]);
    }

    #[Route('/jobs/{id<\d+>}/quick-status', name: 'app_job_offer_quick_status', methods: ['POST'])]
    public function quickStatus(
        JobMatch $match,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertOwnsMatch($account, $match);

        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('quick-status-'.$match->getId(), $submittedToken)) {
            if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
                return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $rawStatus = $request->request->getString('status');
        $status = JobApplicationStatus::tryFrom($rawStatus);
        if ($status === null) {
            if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
                return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
            }

            return $this->redirectToRoute('app_job_offers');
        }

        $match->updateApplicationStatus($status);
        $entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json([
                'success' => true,
                'status' => $status->value,
                'label' => $status->label(),
                'badgeClass' => $status->badgeClass(),
                'icon' => $status->icon(),
            ]);
        }

        $this->addFlash('success', MatchingMessage::APPLICATION_STATUS_UPDATED);

        if ($status === JobApplicationStatus::NOT_INTERESTED) {
            return $this->redirectToRoute('app_job_offers');
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_job_offers');
    }

    #[Route('/jobs/{id<\d+>}/analyze', name: 'app_job_offer_analyze', methods: ['POST'])]
    public function analyze(
        JobMatch $match,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
        $this->assertOwnsMatch($account, $match);
        if (!$this->isCsrfTokenValid('analyze-job-'.$match->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $match->queueSemanticAnalysis();
        $entityManager->flush();
        $id = $match->getId();
        if ($id === null) {
            throw new \RuntimeException(MatchingMessage::MATCH_NOT_FOUND);
        }
        $messageBus->dispatch(new AnalyzeJobMatchMessage($id));
        $this->addFlash('success', MatchingMessage::SEMANTIC_ANALYSIS_QUEUED);

        return $this->redirectToRoute('app_job_offer_show', ['id' => $id]);
    }

    private function assertOwnsMatch(Account $account, JobMatch $match): void
    {
        if ($account->getCandidateProfile()->getId() !== $match->getCandidateProfile()->getId()
            || !$match->getJobOffer()->getSource()->belongsToActiveCv()) {
            throw $this->createNotFoundException(MatchingMessage::MATCH_NOT_FOUND);
        }
    }
}
