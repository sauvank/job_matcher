<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\JobSourceType;
use App\Job\DTO\JobSourceData;
use App\Job\Entity\JobSource;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\JobSourceUrlParser;
use App\Job\Repository\JobSourceRepository;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class JobSourceController extends AbstractController
{
    #[Route('/sources', name: 'app_job_sources', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        JobSourceRepository $repository,
        JobSourceUrlParser $urlParser,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
        $data = new JobSourceData();
        $form = $this->createForm(JobSourceType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $data->name !== null && $data->url !== null) {
            try {
                $provider = $urlParser->detect($data->url);
                $source = new JobSource($data->name, $data->url, $provider);
                $entityManager->persist($source);
                $entityManager->flush();

                $sourceId = $source->getId();
                if ($sourceId === null) {
                    throw new \RuntimeException(JobMessage::SOURCE_NOT_FOUND);
                }

                $messageBus->dispatch(new ImportJobSourceMessage($sourceId));
                $this->addFlash('success', JobMessage::SOURCE_ADDED);

                return $this->redirectToRoute('app_job_sources');
            } catch (\InvalidArgumentException $exception) {
                $form->get('url')->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('job/source/index.html.twig', [
            'form' => $form,
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
