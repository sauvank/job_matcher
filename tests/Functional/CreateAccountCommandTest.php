<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateAccountCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->entityManager = $em;

        $this->entityManager->createQuery('DELETE FROM App\Security\Entity\Account')->execute();
    }

    public function testCreateAdminAccountCommand(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:account:create');
        $tester = new CommandTester($command);

        $tester->execute([
            'email' => 'newadmin@example.test',
            'password' => 'secure-password-123',
            '--admin' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('a été créé avec succès', $tester->getDisplay());

        $account = $this->entityManager->getRepository(Account::class)->findOneBy(['email' => 'newadmin@example.test']);
        self::assertInstanceOf(Account::class, $account);
        self::assertTrue($account->isAdmin());
        self::assertTrue($account->isEmailVerified());
    }

    public function testCreateAccountFailsOnDuplicateEmail(): void
    {
        $account = new Account('existing@example.test');
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:account:create');
        $tester = new CommandTester($command);

        $tester->execute([
            'email' => 'existing@example.test',
            'password' => 'secure-password-123',
        ]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Un compte existe déjà', $tester->getDisplay());
    }
}
