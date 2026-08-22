<?php

declare(strict_types=1);

namespace App\Candidate\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CandidateSkill;
use App\Candidate\Entity\Skill;
use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use App\Candidate\Infrastructure\Persistence\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ManageCandidateSkillService
{
    public function __construct(
        private SkillRepository $skillRepository,
        private SkillNameNormalizer $normalizer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function addOrUpdate(
        CandidateProfile $profile,
        string $name,
        SkillLevel $level,
        SkillCategory $category,
    ): CandidateSkill {
        $name = trim($name);
        $normalizedName = $this->normalizer->normalize($name);
        if ($normalizedName === '') {
            throw new \InvalidArgumentException('The skill name cannot be empty.');
        }

        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            if ($candidateSkill->getSkill()->getNormalizedName() === $normalizedName) {
                $candidateSkill->updateLevel($level);
                $this->entityManager->flush();

                return $candidateSkill;
            }
        }

        $skill = $this->skillRepository->findOneByNormalizedName($normalizedName);
        if ($skill === null) {
            $skill = new Skill($name, $normalizedName, $category);
            $this->entityManager->persist($skill);
        }

        $candidateSkill = new CandidateSkill($profile, $skill, $level);
        $this->entityManager->persist($candidateSkill);
        $this->entityManager->flush();

        return $candidateSkill;
    }

    /** @param array<int, SkillLevel> $levelsBySkillId */
    public function updateLevels(CandidateProfile $profile, array $levelsBySkillId): void
    {
        foreach ($profile->getCandidateSkills() as $candidateSkill) {
            $id = $candidateSkill->getId();
            if ($id !== null && isset($levelsBySkillId[$id])) {
                $candidateSkill->updateLevel($levelsBySkillId[$id]);
            }
        }

        $this->entityManager->flush();
    }

    public function remove(CandidateSkill $candidateSkill): void
    {
        $candidateSkill->getCandidateProfile()->removeCandidateSkill($candidateSkill);
        $this->entityManager->flush();
    }
}
