<?php

declare(strict_types=1);

namespace App\Candidate\Application\Repository;

use App\Candidate\Entity\CvDocument;

interface CvDocumentRepositoryInterface
{
    public function get(int $id): ?CvDocument;
}
