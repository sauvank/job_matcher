<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum ContractType: string
{
    case CDI = 'CDI';
    case CDD = 'CDD';
    case FREELANCE = 'FREELANCE';
    case INTERNSHIP = 'INTERNSHIP';
    case APPRENTICESHIP = 'APPRENTICESHIP';
    case OTHER = 'OTHER';
}
