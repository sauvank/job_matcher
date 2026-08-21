<?php

declare(strict_types=1);

namespace App\Candidate\Application\Service;

use App\Candidate\Application\DTO\AnalyzedSkill;
use App\Candidate\Application\DTO\CvAnalysisResult;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\CvDocument;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\CvStatus;
use App\Candidate\Infrastructure\Persistence\SkillRepository;
use App\Candidate\Translation\CandidateMessage;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ApplyCvAnalysisService
{
    public function __construct(
        private SkillRepository $skillRepository,
        private SkillNameNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param list<string> $selectedSkillNames */
    public function apply(
        CvDocument $document,
        ?string $title,
        ?string $location,
        ?int $yearsOfExperience,
        array $selectedSkillNames,
    ): void {
        if ($document->getStatus() !== CvStatus::READY || $document->getAnalysisResult() === null || $document->getExtractedText() === null) {
            throw new \DomainException(CandidateMessage::ANALYSIS_NOT_APPLICABLE);
        }

        $analysis = CvAnalysisResult::fromArray($document->getAnalysisResult());
        $profile = $document->getCandidateProfile();
        $profile->updateFromCv($title, $location, $yearsOfExperience, $document->getExtractedText());

        foreach ($analysis->skills as $analyzedSkill) {
            if (!in_array($analyzedSkill->name, $selectedSkillNames, true)) {
                continue;
            }
            $this->applySkill($profile->getCandidateSkills()->toArray(), $profile, $analyzedSkill);
        }

        $document->markApplied();
        $this->entityManager->flush();
    }

    /**
     * @param array<int, CandidateSkill> $candidateSkills
     */
    private function applySkill(array $candidateSkills, \App\Candidate\Entity\CandidateProfile $profile, AnalyzedSkill $analyzedSkill): void
    {
        $normalizedName = $this->normalizer->normalize($analyzedSkill->name);
        $skill = $this->skillRepository->findOneByNormalizedName($normalizedName);

        if ($skill === null) {
            $skill = new Skill($analyzedSkill->name, $normalizedName, $analyzedSkill->category);
            $this->entityManager->persist($skill);
        }

        foreach ($candidateSkills as $candidateSkill) {
            if ($candidateSkill->getSkill()->getNormalizedName() === $normalizedName) {
                $candidateSkill->updateFromAnalysis(
                    $analyzedSkill->level,
                    $analyzedSkill->yearsOfExperience,
                    $analyzedSkill->isCoreSkill,
                    $analyzedSkill->confidence,
                );

                return;
            }
        }

        $candidateSkill = new CandidateSkill(
            $profile,
            $skill,
            $analyzedSkill->level,
            $analyzedSkill->yearsOfExperience,
            $analyzedSkill->isCoreSkill,
            $analyzedSkill->confidence,
        );
        $this->entityManager->persist($candidateSkill);
    }
}
