<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum ContractType: string
{
    case CDI = 'CDI';
    case FREELANCE = 'FREELANCE';
    case CDD = 'CDD';
    case APPRENTICESHIP = 'APPRENTICESHIP';
    case INTERNSHIP = 'INTERNSHIP';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CDI => 'CDI',
            self::FREELANCE => 'Freelance',
            self::CDD => 'CDD',
            self::APPRENTICESHIP => 'Alternance',
            self::INTERNSHIP => 'Stage',
            self::OTHER => 'Autre',
        };
    }

    /** @return array<string, string> Label => value */
    public static function choices(): array
    {
        return [
            'CDI' => self::CDI->value,
            'Freelance' => self::FREELANCE->value,
            'CDD' => self::CDD->value,
            'Alternance' => self::APPRENTICESHIP->value,
            'Stage' => self::INTERNSHIP->value,
        ];
    }
}
