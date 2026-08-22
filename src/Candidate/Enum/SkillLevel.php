<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum SkillLevel: string
{
    case BEGINNER = 'BEGINNER';
    case INTERMEDIATE = 'INTERMEDIATE';
    case ADVANCED = 'ADVANCED';
    case EXPERT = 'EXPERT';

    public function label(): string
    {
        return match ($this) {
            self::BEGINNER => 'Débutant',
            self::INTERMEDIATE => 'Intermédiaire',
            self::ADVANCED => 'Avancé',
            self::EXPERT => 'Expert',
        };
    }
}
