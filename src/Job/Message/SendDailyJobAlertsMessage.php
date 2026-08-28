<?php

declare(strict_types=1);

namespace App\Job\Message;

final readonly class SendDailyJobAlertsMessage
{
    public function __construct(
        public string $triggeredBy = 'scheduler',
        public bool $force = false,
        public ?string $targetEmail = null,
    ) {
    }
}
