<?php

declare(strict_types=1);

namespace App\Job\Application\Repository;

use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;

interface JobOfferRepositoryInterface
{
    public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer;

    public function deleteBySource(JobSource $source): void;
}
