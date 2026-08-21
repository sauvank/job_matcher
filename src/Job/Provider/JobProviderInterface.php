<?php

declare(strict_types=1);

namespace App\Job\Provider;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;

interface JobProviderInterface
{
    public function supports(JobProviderType $provider): bool;

    /** @return iterable<NormalizedJobOffer> */
    public function fetch(JobSource $source): iterable;
}
