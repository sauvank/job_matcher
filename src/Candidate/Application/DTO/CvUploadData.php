<?php

declare(strict_types=1);

namespace App\Candidate\Application\DTO;

use App\Candidate\Translation\CandidateMessage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class CvUploadData
{
    #[Assert\NotNull(message: CandidateMessage::UPLOAD_REQUIRED)]
    #[Assert\File(
        maxSize: '10M',
        mimeTypes: [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        maxSizeMessage: CandidateMessage::UPLOAD_TOO_LARGE,
        mimeTypesMessage: CandidateMessage::UPLOAD_INVALID_TYPE,
    )]
    public ?UploadedFile $file = null;
}
