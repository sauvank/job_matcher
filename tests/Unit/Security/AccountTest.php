<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Entity\Account;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testItNormalizesTheEmailAndExposesOnlyTheUserRoleByDefault(): void
    {
        $account = new Account(' Owner@Example.TEST ');

        self::assertSame('owner@example.test', $account->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $account->getRoles());
        self::assertFalse($account->isAdmin());
        self::assertFalse($account->isEmailVerified());
        $account->verifyEmail();
        self::assertTrue($account->isEmailVerified());
        self::assertNotSame($account->getCandidateProfile(), (new Account('other@example.test'))->getCandidateProfile());
    }

    public function testGoogleConnectionCannotBeReplacedByAnotherIdentity(): void
    {
        $account = new Account('owner@example.test');
        self::assertFalse($account->isGoogleConnected());
        $account->connectGoogle('first-subject');
        self::assertTrue($account->isGoogleConnected());

        $this->expectException(\DomainException::class);
        $account->connectGoogle('another-subject');
    }

    public function testAdminRolesManagement(): void
    {
        $account = new Account('user@example.test');
        self::assertFalse($account->isAdmin());
        self::assertSame(['ROLE_USER'], $account->getRoles());

        $account->grantAdmin();
        self::assertTrue($account->isAdmin());
        self::assertContains('ROLE_ADMIN', $account->getRoles());
        self::assertContains('ROLE_USER', $account->getRoles());

        $account->toggleAdmin();
        self::assertFalse($account->isAdmin());

        $account->toggleAdmin();
        self::assertTrue($account->isAdmin());

        $account->revokeAdmin();
        self::assertFalse($account->isAdmin());
    }

    public function testAlertSettingsDefaultsAndCustomization(): void
    {
        $account = new Account('candidate@example.test');
        self::assertTrue($account->isAlertEmailEnabled());
        self::assertSame(70, $account->getAlertScoreThreshold());
        self::assertNull($account->getLastAlertEmailSentAt());

        $account->setAlertEmailEnabled(false);
        self::assertFalse($account->isAlertEmailEnabled());

        $account->setAlertScoreThreshold(85);
        self::assertSame(85, $account->getAlertScoreThreshold());

        $now = new \DateTimeImmutable('2026-08-28 08:00:00');
        $account->setLastAlertEmailSentAt($now);
        self::assertSame($now, $account->getLastAlertEmailSentAt());
    }

    public function testAlertScoreThresholdValidation(): void
    {
        $account = new Account('candidate@example.test');

        $this->expectException(\InvalidArgumentException::class);
        $account->setAlertScoreThreshold(105);
    }
}
