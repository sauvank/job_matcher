<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Entity\Account;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class VerifiedAccountUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof Account && !$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException('Vérifiez votre adresse email avant de vous connecter.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
