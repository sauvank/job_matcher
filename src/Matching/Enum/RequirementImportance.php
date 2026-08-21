<?php

declare(strict_types=1);

namespace App\Matching\Enum;

enum RequirementImportance: string
{
    case REQUIRED = 'REQUIRED';
    case PREFERRED = 'PREFERRED';
    case CONTEXT = 'CONTEXT';
}
