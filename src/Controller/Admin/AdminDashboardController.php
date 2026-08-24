<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Candidate\Entity\CvDocument;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Repository\SchedulerExecutionLogRepository;
use App\Job\Service\SchedulerInspector;
use App\Matching\Entity\JobMatch;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function __invoke(
        AccountRepository $accountRepository,
        SchedulerExecutionLogRepository $executionLogRepository,
        SchedulerInspector $schedulerInspector,
        EntityManagerInterface $entityManager,
    ): Response {
        $totalUsers = $accountRepository->countTotal();
        $verifiedUsers = $accountRepository->countVerified();
        $totalCvDocuments = $entityManager->getRepository(CvDocument::class)->count([]);
        $totalJobSources = $entityManager->getRepository(JobSource::class)->count([]);
        $totalJobOffers = $entityManager->getRepository(JobOffer::class)->count([]);
        $totalJobMatches = $entityManager->getRepository(JobMatch::class)->count([]);

        $statusCounts = $executionLogRepository->getStatusCounts();
        $recentUsers = $accountRepository->findWithFilters(null, null, null);
        $recentUsers = array_slice($recentUsers, 0, 5);

        $recentCronLogs = $executionLogRepository->findLatest(5);
        $schedules = $schedulerInspector->getConfiguredSchedules();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'total_users' => $totalUsers,
                'verified_users' => $verifiedUsers,
                'unverified_users' => $totalUsers - $verifiedUsers,
                'total_cvs' => $totalCvDocuments,
                'total_sources' => $totalJobSources,
                'total_offers' => $totalJobOffers,
                'total_matches' => $totalJobMatches,
                'cron_status_counts' => $statusCounts,
            ],
            'recent_users' => $recentUsers,
            'recent_cron_logs' => $recentCronLogs,
            'schedules' => $schedules,
        ]);
    }
}
