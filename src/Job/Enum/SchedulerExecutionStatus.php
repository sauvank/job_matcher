<?php

declare(strict_types=1);

namespace App\Job\Enum;

enum SchedulerExecutionStatus: string
{
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'En cours',
            self::SUCCESS => 'Succès',
            self::FAILED => 'Échec',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RUNNING => 'badge-info',
            self::SUCCESS => 'badge-success',
            self::FAILED => 'badge-danger',
        };
    }
}
