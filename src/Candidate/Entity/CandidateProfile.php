<?php

declare(strict_types=1);

namespace App\Candidate\Entity;

use App\Candidate\Enum\RemotePolicy;
use App\Candidate\Infrastructure\Persistence\CandidateProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidateProfileRepository::class)]
#[ORM\Table(name: 'candidate_profile')]
final class CandidateProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumSalary = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $preferredContractTypes = [];

    #[ORM\Column(enumType: RemotePolicy::class)]
    private RemotePolicy $preferredRemotePolicy = RemotePolicy::UNKNOWN;

    #[ORM\Column(nullable: true)]
    private ?int $yearsOfExperience = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rawCvText = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CandidateSkill> */
    #[ORM\OneToMany(mappedBy: 'candidateProfile', targetEntity: CandidateSkill::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $candidateSkills;

    /** @var Collection<int, CvDocument> */
    #[ORM\OneToMany(mappedBy: 'candidateProfile', targetEntity: CvDocument::class, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $cvDocuments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->candidateSkills = new ArrayCollection();
        $this->cvDocuments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getMinimumSalary(): ?int
    {
        return $this->minimumSalary;
    }

    /** @return list<string> */
    public function getPreferredContractTypes(): array
    {
        return $this->preferredContractTypes;
    }

    public function getPreferredRemotePolicy(): RemotePolicy
    {
        return $this->preferredRemotePolicy;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->yearsOfExperience;
    }

    public function getRawCvText(): ?string
    {
        return $this->rawCvText;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, CandidateSkill> */
    public function getCandidateSkills(): Collection
    {
        return $this->candidateSkills;
    }

    /** @return Collection<int, CvDocument> */
    public function getCvDocuments(): Collection
    {
        return $this->cvDocuments;
    }

    public function updateFromCv(?string $title, ?string $location, ?int $yearsOfExperience, string $rawCvText): void
    {
        $this->title = $title ?? $this->title;
        $this->location = $location ?? $this->location;
        $this->yearsOfExperience = $yearsOfExperience ?? $this->yearsOfExperience;
        $this->rawCvText = $rawCvText;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param list<string> $preferredContractTypes */
    public function updatePreferences(int $minimumSalary, array $preferredContractTypes, RemotePolicy $remotePolicy): void
    {
        $this->minimumSalary = $minimumSalary;
        $this->preferredContractTypes = $preferredContractTypes;
        $this->preferredRemotePolicy = $remotePolicy;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addCandidateSkill(CandidateSkill $candidateSkill): void
    {
        if (!$this->candidateSkills->contains($candidateSkill)) {
            $this->candidateSkills->add($candidateSkill);
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    /** @param list<string> $normalizedSkillNames */
    public function retainCandidateSkills(array $normalizedSkillNames): void
    {
        foreach ($this->candidateSkills as $candidateSkill) {
            if (!in_array($candidateSkill->getSkill()->getNormalizedName(), $normalizedSkillNames, true)) {
                $this->candidateSkills->removeElement($candidateSkill);
                $this->updatedAt = new \DateTimeImmutable();
            }
        }
    }

    public function addCvDocument(CvDocument $document): void
    {
        if (!$this->cvDocuments->contains($document)) {
            $this->cvDocuments->add($document);
        }
    }

    public function forgetRawCvTextIfMatches(?string $rawCvText): void
    {
        if ($rawCvText !== null && $this->rawCvText === $rawCvText) {
            $this->rawCvText = null;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }
}
