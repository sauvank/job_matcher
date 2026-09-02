<?php

declare(strict_types=1);

namespace App\Candidate\Enum;

enum RemotePolicy: string
{
    case ON_SITE = 'ON_SITE';
    case HYBRID = 'HYBRID';
    case REMOTE = 'REMOTE';
    case FLEXIBLE = 'FLEXIBLE';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::ON_SITE => 'Sur site',
            self::HYBRID => 'Hybride',
            self::REMOTE => 'Télétravail complet',
            self::FLEXIBLE => 'Flexible',
            self::UNKNOWN => 'Aucune préférence',
        };
    }
}
