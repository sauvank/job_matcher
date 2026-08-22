<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Entity\Account;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testItNormalizesTheEmailAndExposesOnlyTheUserRole(): void
    {
        $account = new Account(' Owner@Example.TEST ');

        self::assertSame('owner@example.test', $account->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $account->getRoles());
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
}
