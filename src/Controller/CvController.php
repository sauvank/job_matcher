<?php

declare(strict_types=1);

namespace App\Controller;

use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Application\DTO\CvReviewData;
use App\Candidate\Application\DTO\CvUploadData;
use App\Candidate\Application\Message\ProcessCvMessage;
use App\Candidate\Application\Service\ApplyCvAnalysisService;
use App\Candidate\Application\Storage\CvStorageInterface;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Enum\CvStatus;
use App\Candidate\Infrastructure\Persistence\CvDocumentRepository;
use App\Candidate\Translation\CandidateMessage;
use App\Form\CvReviewType;
use App\Form\CvUploadType;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Turbo\TurboBundle;

final class CvController extends AbstractController
{
    #[Route('/cv', name: 'app_cv_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request $request,
        #[CurrentUser] Account $account,
        CvDocumentRepository $documentRepository,
        CvStorageInterface $storage,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
        $data = new CvUploadData();
        $form = $this->createForm(CvUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $data->file !== null) {
            $profile = $account->getCandidateProfile();
            $storedFile = $storage->store($data->file);

            if ($profile->getId() !== null) {
                $existing = $documentRepository->findOneByProfileAndHash($profile, $storedFile->sha256);
                if ($existing !== null) {
                    $storage->delete($storedFile->storedFilename);
                    $this->addFlash('info', CandidateMessage::UPLOAD_DUPLICATE);

                    return $this->redirectToRoute('app_cv_show', ['id' => $existing->getId()]);
                }
            }

            $document = new CvDocument(
                $profile,
                $storedFile->originalFilename,
                $storedFile->storedFilename,
                $storedFile->mimeType,
                $storedFile->size,
                $storedFile->sha256,
            );
            $entityManager->persist($document);
            $entityManager->flush();

            $documentId = $document->getId();
            if ($documentId === null) {
                throw new \RuntimeException(CandidateMessage::DOCUMENT_NOT_FOUND);
            }

            $messageBus->dispatch(new ProcessCvMessage($documentId));
            $this->addFlash('success', CandidateMessage::UPLOAD_ACCEPTED);

            return $this->redirectToRoute('app_cv_show', ['id' => $documentId]);
        }

        return $this->render('cv/upload.html.twig', [
            'form' => $form,
            'profile' => $account->getCandidateProfile(),
        ]);
    }

    #[Route('/cv/{id<\d+>}', name: 'app_cv_show', methods: ['GET', 'POST'])]
    public function show(
        CvDocument $document,
        #[CurrentUser] Account $account,
        Request $request,
        ApplyCvAnalysisService $applyService,
        ConfigureCandidateJobSearchService $jobSearchService,
    ): Response {
        $this->assertOwnsDocument($account, $document);
        $analysis = null;
        $reviewForm = null;

        if ($document->getAnalysisResult() !== null) {
            $analysis = CvAnalysisResult::fromArray($document->getAnalysisResult());
        }

        if (in_array($document->getStatus(), [CvStatus::READY, CvStatus::APPLIED], true) && $analysis !== null) {
            $reviewData = $document->hasAppliedProfile()
                ? CvReviewData::fromDocument($document, $analysis)
                : CvReviewData::fromAnalysis($analysis);
            $reviewForm = $this->createForm(CvReviewType::class, $reviewData, ['analysis' => $analysis]);
            $reviewForm->handleRequest($request);

            if ($reviewForm->isSubmitted() && $reviewForm->isValid()) {
                $applyService->apply(
                    $document,
                    $reviewData->title,
                    $reviewData->location,
                    $reviewData->yearsOfExperience,
                    $reviewData->selectedSkills,
                    $reviewData->contractTypes,
                );
                $jobSearchService->configure($document->getCandidateProfile());
                $this->addFlash('success', CandidateMessage::ANALYSIS_APPLIED);

                return $this->redirectToRoute('app_candidate_profile');
            }
        }

        return $this->render('cv/show.html.twig', [
            'document' => $document,
            'analysis' => $analysis,
            'reviewForm' => $reviewForm,
        ]);
    }

    #[Route('/cv/{id<\d+>}/file', name: 'app_cv_file', methods: ['GET'])]
    public function serveFile(
        CvDocument $document,
        #[CurrentUser] Account $account,
        CvStorageInterface $storage,
    ): Response {
        $this->assertOwnsDocument($account, $document);
        $filePath = $storage->absolutePath($document->getStoredFilename());
        if (!is_file($filePath)) {
            throw $this->createNotFoundException(CandidateMessage::DOCUMENT_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $document->getOriginalFilename(),
            'cv.pdf',
        );
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', $document->getMimeType() ?: 'application/pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');

        return $response;
    }

    #[Route('/cv/{id<\d+>}/reanalyze', name: 'app_cv_reanalyze', methods: ['POST'])]
    public function reanalyze(
        CvDocument $document,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
        $this->assertOwnsDocument($account, $document);
        if (!$this->isCsrfTokenValid('reanalyze-cv-'.$document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($document->getStatus(), [CvStatus::UPLOADED, CvStatus::EXTRACTING, CvStatus::ANALYZING], true)) {
            $document->requestReanalysis();
            $entityManager->flush();

            $documentId = $document->getId();
            if ($documentId === null) {
                throw new \RuntimeException(CandidateMessage::DOCUMENT_NOT_FOUND);
            }

            $messageBus->dispatch(new ProcessCvMessage($documentId));
            $this->addFlash('success', CandidateMessage::REANALYSIS_ACCEPTED);
        }

        return $this->redirectToRoute('app_cv_show', ['id' => $document->getId()]);
    }

    #[Route('/cv/{id<\d+>}/activate', name: 'app_cv_activate', methods: ['POST'])]
    public function activate(
        CvDocument $document,
        #[CurrentUser] Account $account,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->assertOwnsDocument($account, $document);
        if (!$this->isCsrfTokenValid('activate-cv-'.$document->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$document->hasAppliedProfile()) {
            throw $this->createNotFoundException(CandidateMessage::DOCUMENT_NOT_FOUND);
        }

        $account->getCandidateProfile()->activateCvDocument($document);
        $entityManager->flush();
        $this->addFlash('success', CandidateMessage::DOCUMENT_ACTIVATED);

        return $this->redirectToRoute('app_candidate_profile');
    }

    #[Route('/cv/{id<\d+>}/delete', name: 'app_cv_delete', methods: ['POST'])]
    public function delete(
        CvDocument $document,
        #[CurrentUser] Account $account,
        Request $request,
        CvStorageInterface $storage,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $this->assertOwnsDocument($account, $document);
        if (!$this->isCsrfTokenValid('delete-cv-'.$document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (in_array($document->getStatus(), [CvStatus::UPLOADED, CvStatus::EXTRACTING, CvStatus::ANALYZING], true)) {
            $this->addFlash('error', CandidateMessage::DOCUMENT_DELETE_PROCESSING);

            return $this->redirectToRoute('app_cv_upload');
        }

        $documentId = $document->getId();
        $storedFilename = $document->getStoredFilename();
        $profile = $document->getCandidateProfile();
        if ($profile->getActiveCvDocument() === $document) {
            $replacement = null;
            foreach ($profile->getCvDocuments() as $candidateDocument) {
                if ($candidateDocument !== $document && $candidateDocument->hasAppliedProfile()) {
                    $replacement = $candidateDocument;
                    break;
                }
            }
            if ($replacement === null) {
                $profile->clearActiveCvDocument();
            } else {
                $profile->activateCvDocument($replacement);
            }
        } elseif ($profile->getActiveCvDocument() === null) {
            $profile->forgetRawCvTextIfMatches($document->getExtractedText());
        }
        $entityManager->remove($document);
        $entityManager->flush();
        $storage->delete($storedFilename);

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('cv/delete.stream.html.twig', [
                'documentId' => $documentId,
                'message' => $translator->trans(CandidateMessage::DOCUMENT_DELETED),
            ]);
        }

        $this->addFlash('success', CandidateMessage::DOCUMENT_DELETED);

        return $this->redirectToRoute('app_cv_upload');
    }

    private function assertOwnsDocument(Account $account, CvDocument $document): void
    {
        if ($account->getCandidateProfile()->getId() !== $document->getCandidateProfile()->getId()) {
            throw $this->createNotFoundException(CandidateMessage::DOCUMENT_NOT_FOUND);
        }
    }
}
