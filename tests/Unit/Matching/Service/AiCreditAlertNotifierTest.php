<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Service;

use App\Matching\Service\AiCreditAlertNotifier;
use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class AiCreditAlertNotifierTest extends TestCase
{
    public function testItEmailsAdministratorsOncePerThrottlePeriod(): void
    {
        $admin = new Account('admin@example.test');
        $accounts = $this->createStub(AccountRepository::class);
        $accounts->method('findAdmins')->willReturn([$admin]);

        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email) use (&$sent): void {
            $sent[] = $email;
        });

        $notifier = new AiCreditAlertNotifier($accounts, $mailer, new ArrayAdapter(), new NullLogger(), 'Job Matcher <no-reply@example.test>');

        self::assertTrue($notifier->notifyCreditExhausted('OpenAI'));
        self::assertFalse($notifier->notifyCreditExhausted('OpenAI'));
        self::assertCount(1, $sent);
        self::assertSame(['admin@example.test'], array_map(static fn (Address $address): string => $address->getAddress(), $sent[0]->getTo()));
        self::assertStringContainsString('Crédit IA OpenAI épuisé', (string) $sent[0]->getSubject());
    }

    public function testAMailerFailureDoesNotBreakTheAnalysisAndIsRetriedLater(): void
    {
        $admin = new Account('admin@example.test');
        $accounts = $this->createStub(AccountRepository::class);
        $accounts->method('findAdmins')->willReturn([$admin]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(2))->method('send')->willThrowException(new TransportException('SMTP down'));

        $notifier = new AiCreditAlertNotifier($accounts, $mailer, new ArrayAdapter(), new NullLogger(), 'no-reply@example.test');

        self::assertFalse($notifier->notifyCreditExhausted('OpenAI'));
        self::assertFalse($notifier->notifyCreditExhausted('OpenAI'));
    }
}
