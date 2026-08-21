<?php

declare(strict_types=1);

namespace App\Controller;

use App\Job\Entity\JobSource;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Repository\JobSourceRepository;
use App\Job\Translation\JobMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class JobSourceController extends AbstractController
{
    #[Route('/sources', name: 'app_job_sources', methods: ['GET'])]
    public function index(JobSourceRepository $repository): Response
    {
        return $this->render('job/source/index.html.twig', [
            'sources' => $repository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/sources/{id<\d+>}/sync', name: 'app_job_source_sync', methods: ['POST'])]
    public function sync(JobSource $source, Request $request, MessageBusInterface $messageBus): Response
    {
        if (!$this->isCsrfTokenValid('sync-source-'.$source->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $sourceId = $source->getId();
        if ($sourceId === null) {
            throw new \RuntimeException(JobMessage::SOURCE_NOT_FOUND);
        }

        $messageBus->dispatch(new ImportJobSourceMessage($sourceId));
        $this->addFlash('success', JobMessage::SYNC_DISPATCHED);

        return $this->redirectToRoute('app_job_sources');
    }
}
