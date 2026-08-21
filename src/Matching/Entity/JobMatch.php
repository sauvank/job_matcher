<?php

declare(strict_types=1);

namespace App\Matching\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\DTO\MatchScore;
use App\Matching\Repository\JobMatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobMatchRepository::class)]
#[ORM\Table(name: 'job_match')]
#[ORM\UniqueConstraint(name: 'uniq_job_match_candidate_offer', columns: ['candidate_profile_id', 'job_offer_id'])]
final class JobMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CandidateProfile $candidateProfile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobOffer $jobOffer;

    #[ORM\Column]
    private int $globalScore;

    #[ORM\Column]
    private int $hardCriteriaScore;

    #[ORM\Column(nullable: true)]
    private ?int $semanticScore = null;

    #[ORM\Column]
    private int $stackScore;

    #[ORM\Column]
    private int $experienceScore;

    #[ORM\Column]
    private int $salaryScore;

    #[ORM\Column]
    private int $locationScore;

    #[ORM\Column]
    private int $contractScore;

    #[ORM\Column]
    private int $remoteScore;

    #[ORM\Column]
    private int $backendScore;

    /** @var array<string, list<array{key: string, parameters: array<string, int|string>}>> */
    #[ORM\Column(type: Types::JSON)]
    private array $explanation;

    #[ORM\Column]
    private \DateTimeImmutable $analyzedAt;

    public function __construct(CandidateProfile $candidateProfile, JobOffer $jobOffer, MatchScore $score)
    {
        $this->candidateProfile = $candidateProfile;
        $this->jobOffer = $jobOffer;
        $this->update($score);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobOffer(): JobOffer
    {
        return $this->jobOffer;
    }

    public function getGlobalScore(): int
    {
        return $this->globalScore;
    }

    public function getHardCriteriaScore(): int
    {
        return $this->hardCriteriaScore;
    }

    public function getSemanticScore(): ?int
    {
        return $this->semanticScore;
    }

    public function getStackScore(): int
    {
        return $this->stackScore;
    }

    public function getExperienceScore(): int
    {
        return $this->experienceScore;
    }

    public function getSalaryScore(): int
    {
        return $this->salaryScore;
    }

    public function getLocationScore(): int
    {
        return $this->locationScore;
    }

    public function getContractScore(): int
    {
        return $this->contractScore;
    }

    public function getRemoteScore(): int
    {
        return $this->remoteScore;
    }

    public function getBackendScore(): int
    {
        return $this->backendScore;
    }

    /** @return array<string, list<array{key: string, parameters: array<string, int|string>}>> */
    public function getExplanation(): array
    {
        return $this->explanation;
    }

    public function getAnalyzedAt(): \DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function update(MatchScore $score): void
    {
        $this->globalScore = $score->globalScore;
        $this->hardCriteriaScore = $score->hardCriteriaScore;
        $this->stackScore = $score->stackScore;
        $this->experienceScore = $score->experienceScore;
        $this->salaryScore = $score->salaryScore;
        $this->locationScore = $score->locationScore;
        $this->contractScore = $score->contractScore;
        $this->remoteScore = $score->remoteScore;
        $this->backendScore = $score->backendScore;
        $this->explanation = $score->explanation();
        $this->analyzedAt = new \DateTimeImmutable();
    }
}
