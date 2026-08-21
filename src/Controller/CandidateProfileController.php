<?php

declare(strict_types=1);

namespace App\Controller;

use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use App\Matching\DTO\CvOptimizationReport;
use App\Matching\Repository\JobMatchRepository;
use App\Matching\Service\CvOptimizationReportBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CandidateProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_candidate_profile', methods: ['GET'])]
    public function __invoke(
        CandidateProfileRepository $repository,
        JobMatchRepository $matchRepository,
        CvOptimizationReportBuilder $reportBuilder,
    ): Response {
        $profile = $repository->findDefault();
        $report = $profile === null
            ? CvOptimizationReport::empty()
            : $reportBuilder->build($matchRepository->findCompletedForProfile($profile));

        return $this->render('candidate/profile.html.twig', [
            'profile' => $profile,
            'cvOptimization' => $report,
        ]);
    }
}
