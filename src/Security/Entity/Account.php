<?php

declare(strict_types=1);

namespace App\Security\Entity;

use App\Candidate\Entity\CandidateProfile;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'app_account')]
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

    #[ORM\OneToOne(cascade: ['persist'], orphanRemoval: true)]
    #[ORM\JoinColumn(nullable: false)]
    private CandidateProfile $candidateProfile;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $email)
    {
        $normalizedEmail = mb_strtolower(trim($email));
        if ($normalizedEmail === '') {
            throw new \InvalidArgumentException('The account email cannot be empty.');
        }

        $this->email = $normalizedEmail;
        $this->candidateProfile = new CandidateProfile();
        $this->createdAt = new \DateTimeImmutable();
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
        return ['ROLE_USER'];
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getCandidateProfile(): CandidateProfile
    {
        return $this->candidateProfile;
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

    public function eraseCredentials(): void
    {
    }
}
