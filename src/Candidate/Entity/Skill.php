<?php

declare(strict_types=1);

namespace App\Candidate\Entity;

use App\Candidate\Enum\SkillCategory;
use App\Candidate\Infrastructure\Persistence\SkillRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[ORM\Table(name: 'skill')]
#[ORM\UniqueConstraint(name: 'uniq_skill_normalized_name', columns: ['normalized_name'])]
final class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 120)]
    private string $normalizedName;

    #[ORM\Column(enumType: SkillCategory::class)]
    private SkillCategory $category;

    public function __construct(string $name, string $normalizedName, SkillCategory $category)
    {
        $this->name = $name;
        $this->normalizedName = $normalizedName;
        $this->category = $category;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName;
    }

    public function getCategory(): SkillCategory
    {
        return $this->category;
    }
}
