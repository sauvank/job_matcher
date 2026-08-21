<?php

declare(strict_types=1);

namespace App\Job\Entity;

use App\Job\Enum\JobProviderType;
use App\Job\Repository\JobSourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JobSourceRepository::class)]
#[ORM\Table(name: 'job_source')]
final class JobSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, JobOffer> */
    #[ORM\OneToMany(mappedBy: 'source', targetEntity: JobOffer::class)]
    private Collection $offers;

    public function __construct(string $name, string $url, JobProviderType $provider)
    {
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

    public function getName(): string
    {
        return $this->name;
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

    public function markSyncStarted(): void
    {
        $this->lastSyncStartedAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->updatedAt = $this->lastSyncStartedAt;
    }

    public function completeSync(): void
    {
        $this->lastSuccessAt = new \DateTimeImmutable();
        $this->lastError = null;
        $this->updatedAt = $this->lastSuccessAt;
    }

    public function failSync(string $message): void
    {
        $this->lastError = mb_substr($message, 0, 2000);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
