<?php

declare(strict_types=1);

namespace App\Controller;

use App\Matching\Entity\JobMatch;
use App\Matching\Repository\JobMatchRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
        return $this->render('job/offer/show.html.twig', ['match' => $match]);
    }
}
