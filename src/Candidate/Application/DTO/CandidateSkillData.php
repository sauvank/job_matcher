<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Enum\SkillCategory;
use App\Candidate\Enum\SkillLevel;
use Symfony\Component\Validator\Constraints as Assert;

final class CandidateSkillData
{
    #[Assert\NotBlank(message: 'Renseignez le nom de la compétence.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne doit pas dépasser {{ limit }} caractères.')]
    public string $name = '';

    public SkillLevel $level = SkillLevel::INTERMEDIATE;

    public SkillCategory $category = SkillCategory::OTHER;
}
