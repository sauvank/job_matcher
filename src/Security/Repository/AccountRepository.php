<?php

declare(strict_types=1);

namespace App\Security\Repository;

use App\Security\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<Account> */
final class AccountRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Account) {
            throw new \InvalidArgumentException('Unsupported user type.');
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function loadUserByIdentifier(string $identifier): ?Account
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($identifier))]);
    }
}
