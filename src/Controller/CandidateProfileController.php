<?php

declare(strict_types=1);

namespace App\Controller;

use App\Matching\Repository\JobMatchRepository;
use App\Matching\Service\CvOptimizationReportBuilder;
use App\Security\Entity\Account;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class CandidateProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_candidate_profile', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] Account $account,
        JobMatchRepository $matchRepository,
        CvOptimizationReportBuilder $reportBuilder,
    ): Response {
        $profile = $account->getCandidateProfile();
        $report = $reportBuilder->build($matchRepository->findCompletedForProfile($profile));

        return $this->render('candidate/profile.html.twig', [
            'profile' => $profile,
            'cvOptimization' => $report,
        ]);
    }
}
