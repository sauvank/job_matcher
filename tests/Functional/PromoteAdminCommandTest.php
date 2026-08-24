<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteAdminCommandTest extends KernelTestCase
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

    public function testPromoteAdminCommandGrantsAdminRoleAndVerifiesEmail(): void
    {
        $account = new Account('user@example.test');
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        self::assertFalse($account->isAdmin());
        self::assertFalse($account->isEmailVerified());

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:account:promote-admin');
        $tester = new CommandTester($command);

        $tester->execute(['email' => 'user@example.test']);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('est désormais administrateur', $tester->getDisplay());

        $this->entityManager->refresh($account);
        self::assertTrue($account->isAdmin());
        self::assertTrue($account->isEmailVerified());

        // Demote
        $tester->execute(['email' => 'user@example.test', '--demote' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('ont été retirés', $tester->getDisplay());

        $this->entityManager->refresh($account);
        self::assertFalse($account->isAdmin());
    }

    public function testPromoteAdminCommandFailsWhenUserNotFound(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $command = $application->find('app:account:promote-admin');
        $tester = new CommandTester($command);

        $tester->execute(['email' => 'unknown@example.test']);
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Aucun compte trouvé', $tester->getDisplay());
    }
}
