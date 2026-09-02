<?php

declare(strict_types=1);

namespace App\Candidate\Entity;

use App\Candidate\Enum\CvStatus;
use App\Candidate\Infrastructure\Persistence\CvDocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CvDocumentRepository::class)]
#[ORM\Table(name: 'cv_document')]
#[ORM\UniqueConstraint(name: 'uniq_profile_cv_hash', columns: ['candidate_profile_id', 'sha256'])]
final class CvDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cvDocuments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CandidateProfile $candidateProfile;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 255, unique: true)]
    private string $storedFilename;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column(length: 64)]
    private string $sha256;

    #[ORM\Column(enumType: CvStatus::class)]
    private CvStatus $status = CvStatus::UPLOADED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $extractedText = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $analysisResult = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $analyzer = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $analyzedAt = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $appliedTitle = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $appliedLocation = null;

    #[ORM\Column(nullable: true)]
    private ?int $appliedYearsOfExperience = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $appliedContractTypes = [];

    public function __construct(
        CandidateProfile $candidateProfile,
        string $originalFilename,
        string $storedFilename,
        string $mimeType,
        int $size,
        string $sha256,
    ) {
        $this->candidateProfile = $candidateProfile;
        $this->originalFilename = $originalFilename;
        $this->storedFilename = $storedFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->sha256 = $sha256;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $candidateProfile->addCvDocument($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function getStatus(): CvStatus
    {
        return $this->status;
    }

    public function getExtractedText(): ?string
    {
        return $this->extractedText;
    }

    /** @return array<string, mixed>|null */
    public function getAnalysisResult(): ?array
    {
        return $this->analysisResult;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getAnalyzer(): ?string
    {
        return $this->analyzer;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function getAppliedTitle(): ?string
    {
        return $this->appliedTitle;
    }

    public function getAppliedLocation(): ?string
    {
        return $this->appliedLocation;
    }

    public function getAppliedYearsOfExperience(): ?int
    {
        return $this->appliedYearsOfExperience;
    }

    public function hasAppliedProfile(): bool
    {
        return $this->extractedText !== null && ($this->status === CvStatus::APPLIED
            || $this->appliedTitle !== null
            || $this->appliedLocation !== null
            || $this->appliedYearsOfExperience !== null);
    }

    public function getProcessingProgress(): int
    {
        return match ($this->status) {
            CvStatus::UPLOADED => 10,
            CvStatus::EXTRACTING => 35,
            CvStatus::ANALYZING => 70,
            CvStatus::READY, CvStatus::APPLIED, CvStatus::FAILED => 100,
        };
    }

    public function markExtracting(): void
    {
        $this->status = CvStatus::EXTRACTING;
        $this->errorMessage = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAnalyzing(string $extractedText): void
    {
        $this->status = CvStatus::ANALYZING;
        $this->extractedText = $extractedText;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $analysisResult */
    public function completeAnalysis(array $analysisResult, string $analyzer): void
    {
        $this->status = CvStatus::READY;
        $this->analysisResult = $analysisResult;
        $this->analyzer = $analyzer;
        $this->analyzedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->analyzedAt;
        $this->errorMessage = null;
    }

    /** @param list<string> $appliedContractTypes */
    public function markApplied(?string $title = null, ?string $location = null, ?int $yearsOfExperience = null, array $appliedContractTypes = []): void
    {
        $this->status = CvStatus::APPLIED;
        $this->appliedTitle = $title;
        $this->appliedLocation = $location;
        $this->appliedYearsOfExperience = $yearsOfExperience;
        $this->appliedContractTypes = array_values(array_unique(array_filter($appliedContractTypes, static fn (string $v): bool => $v !== '')));
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param list<string> $appliedContractTypes */
    public function updateAppliedDetails(?string $title, ?string $location, ?int $yearsOfExperience, array $appliedContractTypes = []): void
    {
        $this->appliedTitle = $title;
        $this->appliedLocation = $location;
        $this->appliedYearsOfExperience = $yearsOfExperience;
        $this->appliedContractTypes = array_values(array_unique(array_filter($appliedContractTypes, static fn (string $v): bool => $v !== '')));
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return list<string> */
    public function getAppliedContractTypes(): array
    {
        return $this->appliedContractTypes;
    }

    public function requestReanalysis(): void
    {
        $this->status = CvStatus::UPLOADED;
        $this->analysisResult = null;
        $this->errorMessage = null;
        $this->analyzer = null;
        $this->analyzedAt = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function fail(string $message): void
    {
        $this->status = CvStatus::FAILED;
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
