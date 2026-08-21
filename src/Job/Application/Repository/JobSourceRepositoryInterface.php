<?php

declare(strict_types=1);

namespace App\Job\Application\Repository;

use App\Job\Entity\JobSource;

interface JobSourceRepositoryInterface
{
    public function get(int $id): ?JobSource;
}
