<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum SkillLevel: string
{
    case BEGINNER = 'BEGINNER';
    case INTERMEDIATE = 'INTERMEDIATE';
    case ADVANCED = 'ADVANCED';
    case EXPERT = 'EXPERT';
}
