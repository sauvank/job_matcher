<?php

declare(strict_types=1);

namespace App\Controller;

use App\Job\Repository\JobOfferRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class JobOfferController extends AbstractController
{
    #[Route('/jobs', name: 'app_job_offers', methods: ['GET'])]
    public function __invoke(JobOfferRepository $repository): Response
    {
        return $this->render('job/offer/index.html.twig', ['offers' => $repository->findRecent()]);
    }
}
