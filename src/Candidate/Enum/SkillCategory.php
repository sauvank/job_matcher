<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum SkillCategory: string
{
    case BACKEND = 'BACKEND';
    case FRONTEND = 'FRONTEND';
    case DATABASE = 'DATABASE';
    case DEVOPS = 'DEVOPS';
    case TESTING = 'TESTING';
    case METHODOLOGY = 'METHODOLOGY';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::BACKEND => 'Backend',
            self::FRONTEND => 'Frontend',
            self::DATABASE => 'Base de données',
            self::DEVOPS => 'DevOps',
            self::TESTING => 'Tests',
            self::METHODOLOGY => 'Méthodologie',
            self::OTHER => 'Autre',
        };
    }
}
