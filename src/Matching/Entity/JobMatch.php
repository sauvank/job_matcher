<?php

declare(strict_types=1);

namespace App\Matching\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Entity\JobOffer;
use App\Matching\DTO\MatchScore;
use App\Matching\DTO\SemanticJobAnalysis;
use App\Matching\Enum\JobApplicationStatus;
use App\Matching\Enum\SemanticAnalysisStatus;
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

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $semanticAnalysis = null;

    #[ORM\Column(enumType: SemanticAnalysisStatus::class)]
    private SemanticAnalysisStatus $semanticAnalysisStatus = SemanticAnalysisStatus::NOT_REQUESTED;

    #[ORM\Column(enumType: JobApplicationStatus::class)]
    private JobApplicationStatus $applicationStatus = JobApplicationStatus::UNPROCESSED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statusReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $statusUpdatedAt = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $semanticAnalyzer = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $semanticError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $semanticAnalyzedAt = null;

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

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
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

    /** @return array<string, mixed>|null */
    public function getSemanticAnalysis(): ?array
    {
        return $this->semanticAnalysis;
    }

    public function getSemanticAnalysisStatus(): SemanticAnalysisStatus
    {
        return $this->semanticAnalysisStatus;
    }

    public function getApplicationStatus(): JobApplicationStatus
    {
        return $this->applicationStatus;
    }

    public function getStatusReason(): ?string
    {
        return $this->statusReason;
    }

    public function getStatusUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->statusUpdatedAt;
    }

    public function updateApplicationStatus(JobApplicationStatus $status, ?string $reason = null): void
    {
        $this->applicationStatus = $status;
        $cleanReason = $reason !== null ? trim($reason) : null;
        $this->statusReason = $cleanReason === '' ? null : $cleanReason;
        $this->statusUpdatedAt = new \DateTimeImmutable();
    }

    public function getSemanticAnalyzer(): ?string
    {
        return $this->semanticAnalyzer;
    }

    public function getSemanticError(): ?string
    {
        return $this->semanticError;
    }

    public function getSemanticAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->semanticAnalyzedAt;
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

    public function queueSemanticAnalysis(): void
    {
        $this->semanticAnalysisStatus = SemanticAnalysisStatus::QUEUED;
        $this->semanticError = null;
    }

    public function belongsToActiveCv(): bool
    {
        return $this->jobOffer->getSource()->belongsToActiveCv();
    }

    public function cancelQueuedSemanticAnalysis(): void
    {
        if ($this->semanticAnalysisStatus === SemanticAnalysisStatus::QUEUED) {
            $this->semanticAnalysisStatus = SemanticAnalysisStatus::NOT_REQUESTED;
        }
    }

    public function startSemanticAnalysis(): void
    {
        $this->semanticAnalysisStatus = SemanticAnalysisStatus::RUNNING;
        $this->semanticError = null;
    }

    public function completeSemanticAnalysis(SemanticJobAnalysis $analysis, string $analyzer): void
    {
        $this->semanticAnalysis = $analysis->toArray();
        $this->semanticScore = $analysis->compatibilityScore;
        $this->semanticAnalyzer = $analyzer;
        $this->semanticAnalysisStatus = SemanticAnalysisStatus::COMPLETED;
        $this->semanticError = null;
        $this->semanticAnalyzedAt = new \DateTimeImmutable();
    }

    public function failSemanticAnalysis(string $error): void
    {
        $this->semanticAnalysisStatus = SemanticAnalysisStatus::FAILED;
        $this->semanticError = $error;
    }
}
