<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AuthenticatedWebTestCase extends WebTestCase
{
    protected function loginOwner(KernelBrowser $client): Account
    {
        $account = $this->owner();
        $client->loginUser($account);

        return $account;
    }

    protected function owner(): Account
    {
        return $this->account('owner@example.test');
    }

    protected function account(string $email): Account
    {
        $repository = self::getContainer()->get(AccountRepository::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(AccountRepository::class, $repository);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $passwordHasher);

        $account = $repository->findOneBy(['email' => $email]) ?? new Account($email);
        $account->setPassword($passwordHasher->hashPassword($account, 'correct horse battery staple'));
        if ($account->getId() === null) {
            $entityManager->persist($account);
        }
        $entityManager->flush();

        return $account;
    }
}
