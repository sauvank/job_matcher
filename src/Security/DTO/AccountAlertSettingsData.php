<?php

declare(strict_types=1);

namespace App\Security\DTO;

use App\Security\Entity\Account;
use Symfony\Component\Validator\Constraints as Assert;

final class AccountAlertSettingsData
{
    public bool $alertEmailEnabled = true;

    #[Assert\NotNull(message: 'Veuillez renseigner un seuil de compatibilité.')]
    #[Assert\Range(
        min: 10,
        max: 100,
        notInRangeMessage: 'Le seuil de compatibilité doit être compris entre {{ min }}% et {{ max }}%.'
    )]
    public int $alertScoreThreshold = 70;

    public static function fromAccount(Account $account): self
    {
        $dto = new self();
        $dto->alertEmailEnabled = $account->isAlertEmailEnabled();
        $dto->alertScoreThreshold = $account->getAlertScoreThreshold();

        return $dto;
    }
}
