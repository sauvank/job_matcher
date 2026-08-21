<?php

declare(strict_types=1);

namespace App\Matching\Enum;

enum RequirementCategory: string
{
    case TECHNICAL = 'TECHNICAL';
    case EXPERIENCE = 'EXPERIENCE';
    case RESPONSIBILITY = 'RESPONSIBILITY';
    case EDUCATION = 'EDUCATION';
    case CERTIFICATION = 'CERTIFICATION';
    case DOMAIN = 'DOMAIN';
    case SOFT_SKILL = 'SOFT_SKILL';
    case WORKING_CONDITION = 'WORKING_CONDITION';
}
