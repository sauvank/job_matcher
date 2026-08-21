<?php

declare(strict_types=1);

namespace App\Controller;

use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Entity\JobMatch;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Repository\JobMatchRepository;
use App\Matching\Translation\MatchingMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class JobOfferController extends AbstractController
{
    #[Route('/jobs', name: 'app_job_offers', methods: ['GET'])]
    public function index(JobMatchRepository $repository): Response
    {
        return $this->render('job/offer/index.html.twig', ['matches' => $repository->findRanked()]);
    }

    #[Route('/jobs/{id<\d+>}', name: 'app_job_offer_show', methods: ['GET'])]
    public function show(JobMatch $match): Response
    {
        $semanticAnalysis = $match->getSemanticAnalysis();

        return $this->render('job/offer/show.html.twig', [
            'match' => $match,
            'semanticAnalysis' => $semanticAnalysis === null ? null : SemanticJobAnalysis::fromArray($semanticAnalysis),
        ]);
    }

    #[Route('/jobs/{id<\d+>}/analyze', name: 'app_job_offer_analyze', methods: ['POST'])]
    public function analyze(
        JobMatch $match,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
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
}
