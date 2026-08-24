<?php

declare(strict_types=1);

namespace App\Job\Enum;

enum JobProviderType: string
{
    case FAKE = 'FAKE';
    case HELLOWORK = 'HELLOWORK';
    case INDEED = 'INDEED';
    case APEC = 'APEC';
    case FRANCE_TRAVAIL = 'FRANCE_TRAVAIL';
}
