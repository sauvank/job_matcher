<?php

declare(strict_types=1);

namespace App\Job\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class JobSourceData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Url(protocols: ['https'])]
    #[Assert\Length(max: 2000)]
    public ?string $url = null;
}
