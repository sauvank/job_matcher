<?php

declare(strict_types=1);

namespace App\Security\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationData
{
    #[Assert\NotBlank(message: 'Renseignez votre adresse email.')]
    #[Assert\Email(message: 'Renseignez une adresse email valide.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Choisissez un mot de passe.')]
    #[Assert\Length(min: 12, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM, message: 'Choisissez un mot de passe plus difficile à deviner.')]
    public string $plainPassword = '';
}
