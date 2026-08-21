<?php

declare(strict_types=1);

namespace App\Job\Entity;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Enum\JobOfferStatus;
use App\Job\Repository\JobOfferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\Table(name: 'job_offer')]
#[ORM\UniqueConstraint(name: 'uniq_job_offer_source_external', columns: ['source_id', 'external_id'])]
final class JobOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'offers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private JobSource $source;

    #[ORM\Column(length: 120)]
    private string $externalId;

    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $contractType = null;

    #[ORM\Column(nullable: true)]
    private ?int $minimumSalary = null;

    #[ORM\Column(nullable: true)]
    private ?int $maximumSalary = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $remotePolicy = null;

    #[ORM\Column(nullable: true)]
    private ?int $yearsOfExperience = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validThrough = null;

    #[ORM\Column]
    private \DateTimeImmutable $firstSeenAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(enumType: JobOfferStatus::class)]
    private JobOfferStatus $status = JobOfferStatus::ACTIVE;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $rawPayload = [];

    #[ORM\Column(length: 64)]
    private string $contentHash;

    public function __construct(JobSource $source, NormalizedJobOffer $offer)
    {
        $this->source = $source;
        $this->externalId = $offer->externalId;
        $this->firstSeenAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->firstSeenAt;
        $this->updateFrom($offer);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function getMinimumSalary(): ?int
    {
        return $this->minimumSalary;
    }

    public function getMaximumSalary(): ?int
    {
        return $this->maximumSalary;
    }

    public function getRemotePolicy(): ?string
    {
        return $this->remotePolicy;
    }

    public function getYearsOfExperience(): ?int
    {
        return $this->yearsOfExperience;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getStatus(): JobOfferStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function updateFrom(NormalizedJobOffer $offer): void
    {
        $this->url = $offer->url;
        $this->title = $offer->title;
        $this->company = $offer->company;
        $this->location = $offer->location;
        $this->contractType = $offer->contractType;
        $this->minimumSalary = $offer->minimumSalary;
        $this->maximumSalary = $offer->maximumSalary;
        $this->remotePolicy = $offer->remotePolicy;
        $this->yearsOfExperience = $offer->yearsOfExperience;
        $this->description = $offer->description;
        $this->publishedAt = $offer->publishedAt;
        $this->validThrough = $offer->validThrough;
        $this->rawPayload = $offer->rawPayload;
        $this->contentHash = $offer->contentHash();
        $this->lastSeenAt = new \DateTimeImmutable();
        $this->status = JobOfferStatus::ACTIVE;
    }
}
