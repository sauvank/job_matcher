<?php

declare(strict_types=1);

namespace App\Job\Message;

final readonly class RefreshJobSourcesMessage
{
    public function __construct(
        public string $triggeredBy = 'scheduler',
    ) {
    }
}
