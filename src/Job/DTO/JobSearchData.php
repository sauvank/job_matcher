<?php

declare(strict_types=1);

namespace App\Job\DTO;

use App\Job\Translation\JobMessage;
use Symfony\Component\Validator\Constraints as Assert;

final class JobSearchData
{
    #[Assert\NotBlank(message: JobMessage::SEARCH_TITLE_REQUIRED)]
    #[Assert\Length(max: 120)]
    public string $title = '';
}
