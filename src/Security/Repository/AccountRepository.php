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
class AccountRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
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

    /** @return list<Account> */
    public function findAllOrderedByCreatedAt(): array
    {
        /* @var list<Account> */
        return $this->createQueryBuilder('a')
            ->leftJoin('a.candidateProfile', 'cp')
            ->addSelect('cp')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.emailVerifiedAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Account> */
    public function findWithFilters(?string $query = null, ?string $verified = null, ?string $role = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.candidateProfile', 'cp')
            ->addSelect('cp')
            ->orderBy('a.createdAt', 'DESC');

        if ($query !== null && trim($query) !== '') {
            $qb->andWhere('LOWER(a.email) LIKE :query OR LOWER(cp.title) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }

        if ($verified === 'yes') {
            $qb->andWhere('a.emailVerifiedAt IS NOT NULL');
        } elseif ($verified === 'no') {
            $qb->andWhere('a.emailVerifiedAt IS NULL');
        }

        /** @var list<Account> $results */
        $results = $qb->getQuery()->getResult();

        if ($role === 'admin') {
            $results = array_values(array_filter($results, static fn (Account $a): bool => $a->isAdmin()));
        } elseif ($role === 'user') {
            $results = array_values(array_filter($results, static fn (Account $a): bool => !$a->isAdmin()));
        }

        return $results;
    }

    /** @return list<Account> */
    public function findAccountsForDailyAlerts(): array
    {
        /* @var list<Account> */
        return $this->createQueryBuilder('a')
            ->leftJoin('a.candidateProfile', 'cp')
            ->addSelect('cp')
            ->where('a.alertEmailEnabled = true')
            ->orderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
