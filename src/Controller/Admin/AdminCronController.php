<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Job\Enum\SchedulerExecutionStatus;
use App\Job\Repository\SchedulerExecutionLogRepository;
use App\Job\Service\SchedulerInspector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdminCronController extends AbstractController
{
    #[Route('/admin/cron', name: 'app_admin_cron', methods: ['GET'])]
    public function index(
        Request $request,
        SchedulerInspector $schedulerInspector,
        SchedulerExecutionLogRepository $executionLogRepository,
    ): Response {
        $statusFilter = $request->query->getString('status', 'all');
        $scheduleFilter = $request->query->getString('schedule', 'all');

        $statusEnum = null;
        if ($statusFilter !== 'all') {
            $statusEnum = SchedulerExecutionStatus::tryFrom($statusFilter);
        }

        $schedules = $schedulerInspector->getConfiguredSchedules();
        $logs = $executionLogRepository->findLatest(
            limit: 50,
            status: $statusEnum,
            scheduleName: $scheduleFilter !== 'all' ? $scheduleFilter : null,
        );
        $statusCounts = $executionLogRepository->getStatusCounts();

        return $this->render('admin/cron/index.html.twig', [
            'schedules' => $schedules,
            'logs' => $logs,
            'status_counts' => $statusCounts,
            'filters' => [
                'status' => $statusFilter,
                'schedule' => $scheduleFilter,
            ],
        ]);
    }

    #[Route('/admin/cron/trigger/{scheduleName}', name: 'app_admin_cron_trigger', methods: ['POST'])]
    public function trigger(
        string $scheduleName,
        Request $request,
        SchedulerInspector $schedulerInspector,
    ): Response {
        if (!$this->isCsrfTokenValid('trigger_cron_'.$scheduleName, $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_cron');
        }

        $userIdentifier = $this->getUser()?->getUserIdentifier() ?? 'admin';

        try {
            $schedulerInspector->triggerSchedule($scheduleName, sprintf('admin:%s', $userIdentifier));
            $this->addFlash('success', sprintf('La tâche planifiée « %s » a été déclenchée avec succès.', $scheduleName));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_cron');
    }

    #[Route('/admin/cron/purge', name: 'app_admin_cron_purge', methods: ['POST'])]
    public function purge(
        Request $request,
        SchedulerExecutionLogRepository $executionLogRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('purge_cron_logs', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_admin_cron');
        }

        $days = max(1, $request->request->getInt('days', 30));
        $before = new \DateTimeImmutable(sprintf('-%d days', $days));
        $deleted = $executionLogRepository->purgeOlderThan($before);

        $this->addFlash('success', sprintf('%d entrée(s) de journal antérieures à %d jours ont été purgées.', $deleted, $days));

        return $this->redirectToRoute('app_admin_cron');
    }
}
