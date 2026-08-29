<?php

declare(strict_types=1);

namespace App\Security\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Security\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'app_account')]
#[ORM\Index(name: 'idx_account_alert_email_enabled', columns: ['alert_email_enabled'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte utilise déjà cette adresse email.')]
final class Account implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var non-empty-string */
    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $googleSubject = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\OneToOne(cascade: ['persist'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: false)]
    private CandidateProfile $candidateProfile;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $alertEmailEnabled = true;

    #[ORM\Column(options: ['default' => 70])]
    private int $alertScoreThreshold = 70;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastAlertEmailSentAt = null;

    public function __construct(string $email)
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            throw new \InvalidArgumentException('The account email cannot be empty.');
        }

        $this->email = $normalizedEmail;
        $this->candidateProfile = new CandidateProfile();
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $cleaned = array_values(array_filter(
            array_unique($roles),
            static fn (string $role): bool => $role !== 'ROLE_USER' && trim($role) !== ''
        ));
        $this->roles = $cleaned;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function grantAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->roles[] = 'ROLE_ADMIN';
            $this->roles = array_values(array_unique($this->roles));
        }
    }

    public function revokeAdmin(): void
    {
        $this->roles = array_values(array_filter(
            $this->roles,
            static fn (string $role): bool => $role !== 'ROLE_ADMIN'
        ));
    }

    public function toggleAdmin(): void
    {
        if ($this->isAdmin()) {
            $this->revokeAdmin();
        } else {
            $this->grantAdmin();
        }
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function connectGoogle(string $subject): void
    {
        if ($this->googleSubject !== null && $this->googleSubject !== $subject) {
            throw new \DomainException('Ce compte est déjà associé à un autre compte Google.');
        }

        $this->googleSubject = $subject;
    }

    public function isGoogleConnected(): bool
    {
        return $this->googleSubject !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt ??= new \DateTimeImmutable();
    }

    public function unverifyEmail(): void
    {
        $this->emailVerifiedAt = null;
    }

    public function isAlertEmailEnabled(): bool
    {
        return $this->alertEmailEnabled;
    }

    public function setAlertEmailEnabled(bool $alertEmailEnabled): void
    {
        $this->alertEmailEnabled = $alertEmailEnabled;
    }

    public function getAlertScoreThreshold(): int
    {
        return $this->alertScoreThreshold;
    }

    public function setAlertScoreThreshold(int $alertScoreThreshold): void
    {
        if ($alertScoreThreshold < 1 || $alertScoreThreshold > 100) {
            throw new \InvalidArgumentException('Le seuil de compatibilité doit être compris entre 1 et 100.');
        }

        $this->alertScoreThreshold = $alertScoreThreshold;
    }

    public function getLastAlertEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->lastAlertEmailSentAt;
    }

    public function setLastAlertEmailSentAt(?\DateTimeImmutable $lastAlertEmailSentAt): void
    {
        $this->lastAlertEmailSentAt = $lastAlertEmailSentAt;
    }

    public function eraseCredentials(): void
    {
    }
}
