<?php

declare(strict_types=1);

namespace App\Controller;

use App\Matching\Entity\JobMatch;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Repository\JobMatchRepository;
use App\Matching\Translation\MatchingMessage;
use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class JobApplicationController extends AbstractController
{
    /** @var list<JobApplicationStatus> */
    public const KANBAN_COLUMNS = [
        JobApplicationStatus::INTERESTED,
        JobApplicationStatus::APPLIED,
        JobApplicationStatus::WAITING,
        JobApplicationStatus::INTERVIEW,
        JobApplicationStatus::ACCEPTED,
        JobApplicationStatus::REJECTED,
    ];

    #[Route('/applications', name: 'app_applications_kanban', methods: ['GET'])]
    public function index(#[CurrentUser] Account $account, JobMatchRepository $repository): Response
    {
        $profile = $account->getCandidateProfile();
        $matches = $repository->findForKanban($profile);

        /** @var array<string, list<JobMatch>> $columns */
        $columns = [
            JobApplicationStatus::INTERESTED->value => [],
            JobApplicationStatus::APPLIED->value => [],
            JobApplicationStatus::WAITING->value => [],
            JobApplicationStatus::INTERVIEW->value => [],
            JobApplicationStatus::ACCEPTED->value => [],
            JobApplicationStatus::REJECTED->value => [],
        ];

        $searchLabels = [];
        $activeApplicationsCount = 0;

        foreach ($matches as $match) {
            $status = $match->getApplicationStatus();
            $label = $match->getJobOffer()->getSource()->getSearchLabel();
            if (!in_array($label, $searchLabels, true)) {
                $searchLabels[] = $label;
            }

            if (isset($columns[$status->value])) {
                $columns[$status->value][] = $match;
            }

            if (in_array($status, [
                JobApplicationStatus::INTERESTED,
                JobApplicationStatus::APPLIED,
                JobApplicationStatus::WAITING,
                JobApplicationStatus::INTERVIEW,
                JobApplicationStatus::ACCEPTED,
            ], true)) {
                ++$activeApplicationsCount;
            }
        }

        return $this->render('job/application/kanban.html.twig', [
            'columns' => $columns,
            'kanbanStatuses' => self::KANBAN_COLUMNS,
            'searchLabels' => $searchLabels,
            'totalActiveCount' => $activeApplicationsCount,
            'totalCount' => count($matches),
        ]);
    }

    #[Route('/applications/{id<\d+>}/note', name: 'app_job_offer_update_note', methods: ['POST'])]
    public function updateNote(
        JobMatch $match,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertOwnsMatch($account, $match);

        $submittedToken = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid('update-note-'.$match->getId(), $submittedToken)) {
            if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
                return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_BAD_REQUEST);
            }
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $note = trim($request->request->getString('note'));
        $match->updateApplicationStatus($match->getApplicationStatus(), $note !== '' ? $note : null);
        $entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return $this->json([
                'success' => true,
                'matchId' => $match->getId(),
                'note' => $match->getStatusReason(),
            ]);
        }

        $this->addFlash('success', MatchingMessage::APPLICATION_STATUS_UPDATED);

        return $this->redirectToRoute('app_applications_kanban');
    }

    private function assertOwnsMatch(Account $account, JobMatch $match): void
    {
        if ($match->getCandidateProfile()->getId() !== $account->getCandidateProfile()->getId()) {
            throw $this->createNotFoundException(MatchingMessage::MATCH_NOT_FOUND);
        }
    }
}
