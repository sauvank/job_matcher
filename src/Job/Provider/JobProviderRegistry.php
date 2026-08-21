<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\Enum\JobProviderType;
use App\Job\Translation\JobMessage;

final readonly class JobProviderRegistry
{
    /** @param iterable<JobProviderInterface> $providers */
    public function __construct(private iterable $providers)
    {
    }

    public function get(JobProviderType $type): JobProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($type)) {
                return $provider;
            }
        }

        throw new \InvalidArgumentException(JobMessage::UNSUPPORTED_PROVIDER);
    }
}
