<?php

declare(strict_types=1);

namespace App\Controller;

use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CandidateProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_candidate_profile', methods: ['GET'])]
    public function __invoke(CandidateProfileRepository $repository): Response
    {
        return $this->render('candidate/profile.html.twig', ['profile' => $repository->findDefault()]);
    }
}
