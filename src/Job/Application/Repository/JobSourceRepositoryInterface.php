<?php

declare(strict_types=1);

namespace App\Job\Application\Repository;

use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;

interface JobSourceRepositoryInterface
{
    public function get(int $id): ?JobSource;

    public function findOneByProvider(JobProviderType $provider): ?JobSource;
}
