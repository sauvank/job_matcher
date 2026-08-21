<?php

declare(strict_types=1);

namespace App\Candidate\Infrastructure\Persistence;

use App\Candidate\Application\Repository\CvDocumentRepositoryInterface;
use App\Candidate\Entity\CandidateProfile;
use App\Candidate\Entity\CvDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CvDocument> */
final class CvDocumentRepository extends ServiceEntityRepository implements CvDocumentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CvDocument::class);
    }

    public function findOneByProfileAndHash(CandidateProfile $profile, string $sha256): ?CvDocument
    {
        return $this->findOneBy(['candidateProfile' => $profile, 'sha256' => $sha256]);
    }

    public function get(int $id): ?CvDocument
    {
        return $this->find($id);
    }
}
