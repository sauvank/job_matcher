<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Job\Entity\SchedulerExecutionLog;
use App\Security\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->entityManager = $em;

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);
        $this->passwordHasher = $hasher;

        $this->entityManager->createQuery('DELETE FROM App\Job\Entity\SchedulerExecutionLog')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Security\Entity\Account')->execute();
    }

    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/connexion');

        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('/connexion');

        $this->client->request('GET', '/admin/cron');
        self::assertResponseRedirects('/connexion');
    }

    public function testRegularUserIsDeniedAccessToAdmin(): void
    {
        $user = $this->createAccount('regular@example.test', isAdmin: false);
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/cron');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboardWithMetrics(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $this->createAccount('candidate@example.test', isAdmin: false);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
        self::assertSelectorTextContains('.stat-number', '2');
        self::assertAnySelectorTextContains('table', 'candidate@example.test');
    }

    public function testAdminCanSearchAndFilterUsers(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $this->createAccount('alice@example.test', isAdmin: false, isVerified: true);
        $this->createAccount('bob@example.test', isAdmin: false, isVerified: false);
        $this->client->loginUser($admin);

        // All users
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('table', 'alice@example.test');
        self::assertAnySelectorTextContains('table', 'bob@example.test');

        // Filter by keyword
        $this->client->request('GET', '/admin/users?q=alice');
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('table', 'alice@example.test');
        self::assertStringNotContainsString('bob@example.test', (string) $this->client->getResponse()->getContent());

        // Filter by verification status
        $this->client->request('GET', '/admin/users?verified=no');
        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('table', 'bob@example.test');
        self::assertStringNotContainsString('alice@example.test', (string) $this->client->getResponse()->getContent());
    }

    public function testAdminCanViewUserDetails(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $candidate = $this->createAccount('candidate@example.test', isAdmin: false);
        $candidate->getCandidateProfile()->updateFromCv('Tech Lead PHP', 'Nantes', 8, 'Full stack CV text');
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('/admin/users/%d', (int) $candidate->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'candidate@example.test');
        self::assertAnySelectorTextContains('.profile-grid strong', 'Tech Lead PHP');
        self::assertAnySelectorTextContains('.profile-grid strong', 'Nantes');
    }

    public function testAdminCanToggleAdminRoleAndVerifyEmail(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $user = $this->createAccount('unverified@example.test', isAdmin: false, isVerified: false);
        $userId = (int) $user->getId();
        $this->client->loginUser($admin);

        // Verify email
        $crawler = $this->client->request('GET', '/admin/users');
        $verifyForm = $crawler->filter(sprintf('form[action="/admin/users/%d/verify-email"]', $userId))->form();

        $this->client->submit($verifyForm);
        self::assertResponseRedirects('/admin/users');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'L\'adresse email de unverified@example.test a été validée.');

        $reloaded = $this->reloadAccount($userId);
        self::assertTrue($reloaded->isEmailVerified());

        // Promote to admin
        $toggleForm = $crawler->filter(sprintf('form[action="/admin/users/%d/toggle-admin"]', $userId))->form();
        $this->client->submit($toggleForm);
        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'a été promu administrateur');

        $reloaded = $this->reloadAccount($userId);
        self::assertTrue($reloaded->isAdmin());
    }

    public function testAdminCannotRevokeOwnAdminStatus(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $adminId = (int) $admin->getId();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/users');
        $toggleForm = $crawler->filter(sprintf('form[action="/admin/users/%d/toggle-admin"]', $adminId))->form();
        $this->client->submit($toggleForm);
        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-error', 'Vous ne pouvez pas révoquer vos propres privilèges');

        $reloaded = $this->reloadAccount($adminId);
        self::assertTrue($reloaded->isAdmin());
    }

    public function testAdminCanImpersonateUserAndExit(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $targetUser = $this->createAccount('target@example.test', isAdmin: false);
        $this->client->loginUser($admin);

        // Impersonate
        $this->client->request('GET', '/?_switch_user=target@example.test');
        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();

        // Check impersonation banner is displayed
        self::assertSelectorExists('.impersonation-bar');
        self::assertSelectorTextContains('.impersonation-bar', 'target@example.test');

        // Exit impersonation
        $this->client->request('GET', '/admin/users?_switch_user=_exit');
        self::assertResponseRedirects('http://localhost/admin/users');
        $this->client->followRedirect();

        self::assertSelectorNotExists('.impersonation-bar');
        self::assertSelectorTextContains('.site-header', 'Administration');
    }

    public function testAdminCanViewCronSchedulesAndTriggerJobSync(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/cron');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tâches Planifiées & Cron');
        self::assertSelectorTextContains('.schedule-card', 'job_sync');

        // Trigger manual execution
        $triggerForm = $crawler->filter('form[action="/admin/cron/trigger/job_sync"]')->form();
        $this->client->submit($triggerForm);
        self::assertResponseRedirects('/admin/cron');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'La tâche planifiée « job_sync » a été déclenchée avec succès.');
    }

    public function testAdminCanPurgeOldCronLogs(): void
    {
        $admin = $this->createAccount('admin@example.test', isAdmin: true);
        $this->client->loginUser($admin);

        $oldLog = new SchedulerExecutionLog('job_sync', 'App\\Job\\Message\\RefreshJobSourcesMessage', 'scheduler');
        $oldLog->complete(2, '2 sources');
        // Set startedAt to 40 days ago
        $rProp = new \ReflectionProperty($oldLog, 'startedAt');
        $rProp->setValue($oldLog, new \DateTimeImmutable('-40 days'));
        $this->entityManager->persist($oldLog);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/admin/cron');
        $purgeForm = $crawler->filter('form[action="/admin/cron/purge"]')->form();
        $this->client->submit($purgeForm);
        self::assertResponseRedirects('/admin/cron');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'entrée(s) de journal antérieures à 30 jours ont été purgées.');
    }

    private function createAccount(string $email, bool $isAdmin = false, bool $isVerified = true): Account
    {
        $account = new Account($email);
        $account->setPassword($this->passwordHasher->hashPassword($account, 'password123'));
        if ($isVerified) {
            $account->verifyEmail();
        }
        if ($isAdmin) {
            $account->grantAdmin();
        }

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }

    private function reloadAccount(int $id): Account
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $account = $em->find(Account::class, $id);
        self::assertInstanceOf(Account::class, $account);

        return $account;
    }
}
