<?php

declare(strict_types=1);

namespace App\Job\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use App\Job\Enum\JobProviderType;
use App\Job\Enum\JobSourceSyncStatus;
use App\Job\Repository\JobSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobSourceRepository::class)]
#[ORM\Table(name: 'job_source')]
#[ORM\UniqueConstraint(name: 'uniq_job_source_cv_url', columns: ['cv_document_id', 'url'])]
final class JobSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CandidateProfile $candidateProfile;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CvDocument $cvDocument;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    #[ORM\Column(enumType: JobProviderType::class)]
    private JobProviderType $provider;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncStartedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(enumType: JobSourceSyncStatus::class)]
    private JobSourceSyncStatus $syncStatus = JobSourceSyncStatus::IDLE;

    #[ORM\Column]
    private int $processedOfferCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, JobOffer> */
    #[ORM\OneToMany(mappedBy: 'source', targetEntity: JobOffer::class)]
    private Collection $offers;

    public function __construct(CandidateProfile $candidateProfile, string $name, string $url, JobProviderType $provider, ?CvDocument $cvDocument = null)
    {
        $this->candidateProfile = $candidateProfile;
        $this->cvDocument = $cvDocument;
        $this->name = $name;
        $this->url = $url;
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->offers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
    }

    public function getCvDocument(): ?CvDocument
    {
        return $this->cvDocument;
    }

    public function belongsToActiveCv(): bool
    {
        return $this->cvDocument === $this->candidateProfile->getActiveCvDocument();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSearchLabel(): string
    {
        $separator = ' — ';
        $firstSeparator = mb_strpos($this->name, $separator);
        $lastSeparator = mb_strrpos($this->name, $separator);
        if ($firstSeparator === false || $lastSeparator === false || $firstSeparator === $lastSeparator) {
            return $this->name;
        }

        $start = $firstSeparator + mb_strlen($separator);

        return mb_substr($this->name, $start, $lastSeparator - $start);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getProvider(): JobProviderType
    {
        return $this->provider;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLastSyncStartedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncStartedAt;
    }

    public function getLastSuccessAt(): ?\DateTimeImmutable
    {
        return $this->lastSuccessAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getSyncStatus(): JobSourceSyncStatus
    {
        return $this->syncStatus;
    }

    public function getProcessedOfferCount(): int
    {
        return $this->processedOfferCount;
    }

    public function isSyncPending(): bool
    {
        return in_array($this->syncStatus, [JobSourceSyncStatus::QUEUED, JobSourceSyncStatus::RUNNING], true);
    }

    public function updateSearch(string $name, string $url): void
    {
        $this->name = $name;
        $this->url = $url;
        $this->lastSyncStartedAt = null;
        $this->lastSuccessAt = null;
        $this->lastError = null;
        $this->syncStatus = JobSourceSyncStatus::IDLE;
        $this->processedOfferCount = 0;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function queueSync(): void
    {
        $this->syncStatus = JobSourceSyncStatus::QUEUED;
        $this->processedOfferCount = 0;
        $this->lastError = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancelQueuedSync(): void
    {
        if ($this->syncStatus === JobSourceSyncStatus::QUEUED) {
            $this->syncStatus = JobSourceSyncStatus::IDLE;
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function markSyncStarted(): void
    {
        $this->syncStatus = JobSourceSyncStatus::RUNNING;
        $this->processedOfferCount = 0;
        $this->lastSyncStartedAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->updatedAt = $this->lastSyncStartedAt;
    }

    public function recordProcessedOffer(): void
    {
        ++$this->processedOfferCount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function completeSync(): void
    {
        $this->syncStatus = JobSourceSyncStatus::SUCCEEDED;
        $this->lastSuccessAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->updatedAt = $this->lastSuccessAt;
    }

    public function failSync(string $message): void
    {
        $this->syncStatus = JobSourceSyncStatus::FAILED;
        $this->lastError = mb_substr($message, 0, 2000);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
