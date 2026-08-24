<?php

declare(strict_types=1);

namespace App\Job\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ManualJobOfferData
{
    #[Assert\NotBlank(message: 'Collez l’URL de l’annonce.')]
    #[Assert\Url(protocols: ['http', 'https'], requireTld: true, message: 'Utilisez une URL HTTP ou HTTPS valide.')]
    #[Assert\Length(max: 2048)]
    public string $url = '';

    #[Assert\NotBlank(message: 'Renseignez l’intitulé du poste.')]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Length(max: 255)]
    public string $company = '';

    #[Assert\Length(max: 255)]
    public string $location = '';

    #[Assert\NotBlank(message: 'Collez le contenu de l’annonce.')]
    #[Assert\Length(
        min: 50,
        max: 100000,
        minMessage: 'Le contenu de l’annonce doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le contenu de l’annonce ne doit pas dépasser {{ limit }} caractères.',
    )]
    public string $description = '';
}
