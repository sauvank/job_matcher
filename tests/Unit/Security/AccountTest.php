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
    }

    public function testGoogleConnectionCannotBeReplacedByAnotherIdentity(): void
    {
        $account = new Account('owner@example.test');
        $account->connectGoogle('first-subject');

        $this->expectException(\DomainException::class);
        $account->connectGoogle('another-subject');
    }
}
