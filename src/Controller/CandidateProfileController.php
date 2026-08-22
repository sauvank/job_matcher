<?php

declare(strict_types=1);

namespace App\Controller;

use App\Candidate\Application\DTO\CandidateSkillData;
use App\Candidate\Application\Service\ManageCandidateSkillService;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Enum\SkillLevel;
use App\Candidate\Translation\CandidateMessage;
use App\Form\CandidateSkillType;
use App\Matching\Repository\JobMatchRepository;
use App\Matching\Service\CvOptimizationReportBuilder;
use App\Security\Entity\Account;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
            'skillForm' => $this->createForm(CandidateSkillType::class, new CandidateSkillData(), [
                'action' => $this->generateUrl('app_candidate_skill_add'),
            ]),
            'skillLevels' => SkillLevel::cases(),
        ]);
    }

    #[Route('/profile/skills', name: 'app_candidate_skill_add', methods: ['POST'])]
    public function addSkill(
        Request $request,
        #[CurrentUser] Account $account,
        ManageCandidateSkillService $skillService,
    ): Response {
        $data = new CandidateSkillData();
        $form = $this->createForm(CandidateSkillType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $skillService->addOrUpdate($account->getCandidateProfile(), $data->name, $data->level, $data->category);
            $this->addFlash('success', CandidateMessage::SKILL_SAVED);
        } else {
            $this->addFlash('error', CandidateMessage::SKILL_INVALID);
        }

        return $this->redirectToRoute('app_candidate_profile');
    }

    #[Route('/profile/skills/{id<\d+>}/level', name: 'app_candidate_skill_level', methods: ['POST'])]
    public function updateSkillLevel(
        CandidateSkill $candidateSkill,
        Request $request,
        #[CurrentUser] Account $account,
        ManageCandidateSkillService $skillService,
    ): Response {
        $this->assertOwnsSkill($account, $candidateSkill);
        if (!$this->isCsrfTokenValid('level-skill-'.$candidateSkill->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $level = SkillLevel::tryFrom($request->request->getString('level'));
        if ($level === null) {
            $this->addFlash('error', CandidateMessage::SKILL_INVALID);
        } else {
            $skillService->updateLevel($candidateSkill, $level);
            $this->addFlash('success', CandidateMessage::SKILL_LEVEL_UPDATED);
        }

        return $this->redirectToRoute('app_candidate_profile');
    }

    #[Route('/profile/skills/{id<\d+>}/delete', name: 'app_candidate_skill_delete', methods: ['POST'])]
    public function deleteSkill(
        CandidateSkill $candidateSkill,
        Request $request,
        #[CurrentUser] Account $account,
        ManageCandidateSkillService $skillService,
    ): Response {
        $this->assertOwnsSkill($account, $candidateSkill);
        if (!$this->isCsrfTokenValid('delete-skill-'.$candidateSkill->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $skillService->remove($candidateSkill);
        $this->addFlash('success', CandidateMessage::SKILL_DELETED);

        return $this->redirectToRoute('app_candidate_profile');
    }

    private function assertOwnsSkill(Account $account, CandidateSkill $candidateSkill): void
    {
        if ($account->getCandidateProfile()->getId() !== $candidateSkill->getCandidateProfile()->getId()) {
            throw $this->createNotFoundException(CandidateMessage::SKILL_NOT_FOUND);
        }
    }
}
