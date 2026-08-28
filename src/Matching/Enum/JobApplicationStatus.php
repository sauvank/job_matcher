<?php

declare(strict_types=1);

namespace App\Matching\Enum;

enum JobApplicationStatus: string
{
    case UNPROCESSED = 'UNPROCESSED';
    case INTERESTED = 'INTERESTED';
    case NOT_INTERESTED = 'NOT_INTERESTED';
    case APPLIED = 'APPLIED';
    case WAITING = 'WAITING';
    case INTERVIEW = 'INTERVIEW';
    case REJECTED = 'REJECTED';
    case ACCEPTED = 'ACCEPTED';

    public function label(): string
    {
        return match ($this) {
            self::UNPROCESSED => 'À traiter',
            self::INTERESTED => 'M’intéresse',
            self::NOT_INTERESTED => 'Ne m’intéresse pas',
            self::APPLIED => 'Candidaté',
            self::WAITING => 'En attente',
            self::INTERVIEW => 'Entretien',
            self::REJECTED => 'Refusé',
            self::ACCEPTED => 'Accepté',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::UNPROCESSED => 'badge-neutral',
            self::INTERESTED => 'badge-info',
            self::NOT_INTERESTED => 'badge-neutral',
            self::APPLIED => 'badge-primary',
            self::WAITING => 'badge-warning',
            self::INTERVIEW => 'badge-warning',
            self::REJECTED => 'badge-danger',
            self::ACCEPTED => 'badge-success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::UNPROCESSED => '📋',
            self::INTERESTED => '⭐',
            self::NOT_INTERESTED => '🚫',
            self::APPLIED => '📨',
            self::WAITING => '⏳',
            self::INTERVIEW => '🗣️',
            self::REJECTED => '❌',
            self::ACCEPTED => '🎉',
        };
    }
}
