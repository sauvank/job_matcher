<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Entity\CandidateProfile;
use Symfony\Component\Validator\Constraints as Assert;

final class CandidateProfileDetailsData
{
    /** @param list<string> $preferredContractTypes */
    public function __construct(
        #[Assert\Length(max: 160, maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
        public ?string $title = null,
        #[Assert\Length(max: 160, maxMessage: 'La localisation ne doit pas dépasser {{ limit }} caractères.')]
        public ?string $location = null,
        #[Assert\Range(min: 0, max: 80, notInRangeMessage: 'L’expérience doit être comprise entre {{ min }} et {{ max }} ans.')]
        public ?int $yearsOfExperience = null,
        public array $preferredContractTypes = [],
    ) {
    }

    public static function fromProfile(CandidateProfile $profile): self
    {
        return new self(
            $profile->getTitle(),
            $profile->getLocation(),
            $profile->getYearsOfExperience(),
            $profile->getPreferredContractTypes(),
        );
    }
}
