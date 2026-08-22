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
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Turbo\TurboBundle;

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

        return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'skills']);
    }

    #[Route('/profile/skills/levels', name: 'app_candidate_skill_levels', methods: ['POST'])]
    public function updateSkillLevels(
        Request $request,
        #[CurrentUser] Account $account,
        ManageCandidateSkillService $skillService,
        TranslatorInterface $translator,
    ): Response {
        if (!$this->isCsrfTokenValid('update-skill-levels', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $profile = $account->getCandidateProfile();
        $ownedSkillIds = [];
        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            if ($candidateSkill->getId() !== null) {
                $ownedSkillIds[$candidateSkill->getId()] = true;
            }
        }

        $levelsBySkillId = [];
        foreach ($request->request->all('levels') as $skillId => $submittedLevel) {
            $skillId = filter_var($skillId, FILTER_VALIDATE_INT);
            $level = is_string($submittedLevel) ? SkillLevel::tryFrom($submittedLevel) : null;
            if ($skillId === false || !isset($ownedSkillIds[$skillId])) {
                throw $this->createNotFoundException(CandidateMessage::SKILL_NOT_FOUND);
            }
            if ($level === null) {
                return $this->skillLevelsResponse($request, $translator, 'error', CandidateMessage::SKILL_INVALID);
            }

            $levelsBySkillId[$skillId] = $level;
        }

        $skillService->updateLevels($profile, $levelsBySkillId);

        return $this->skillLevelsResponse($request, $translator, 'success', CandidateMessage::SKILL_LEVELS_UPDATED);
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

        return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'skills']);
    }

    private function skillLevelsResponse(
        Request $request,
        TranslatorInterface $translator,
        string $type,
        string $message,
    ): Response {
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('candidate/skill_levels.stream.html.twig', [
                'type' => $type,
                'message' => $translator->trans($message),
            ]);
        }

        $this->addFlash($type, $message);

        return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'skills']);
    }

    private function assertOwnsSkill(Account $account, CandidateSkill $candidateSkill): void
    {
        if ($account->getCandidateProfile()->getId() !== $candidateSkill->getCandidateProfile()->getId()) {
            throw $this->createNotFoundException(CandidateMessage::SKILL_NOT_FOUND);
        }
    }
}
