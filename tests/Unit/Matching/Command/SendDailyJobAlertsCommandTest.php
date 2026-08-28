<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Command;

use App\Matching\Command\SendDailyJobAlertsCommand;
use App\Matching\Service\DailyJobAlertService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SendDailyJobAlertsCommandTest extends TestCase
{
    public function testExecuteCommandRunsServiceAndReportsSuccess(): void
    {
        /** @var DailyJobAlertService&MockObject $alertService */
        $alertService = $this->createMock(DailyJobAlertService::class);
        $alertService->expects(self::once())
            ->method('sendDailyAlerts')
            ->with(
                self::isNull(),
                false,
                null
            )
            ->willReturn(3);

        $command = new SendDailyJobAlertsCommand($alertService);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('3 email(s) d’alerte envoyé(s) avec succès.', $tester->getDisplay());
    }

    public function testExecuteCommandWithOptions(): void
    {
        /** @var DailyJobAlertService&MockObject $alertService */
        $alertService = $this->createMock(DailyJobAlertService::class);
        $alertService->expects(self::once())
            ->method('sendDailyAlerts')
            ->with(
                self::isNull(),
                true,
                'target@example.test'
            )
            ->willReturn(1);

        $command = new SendDailyJobAlertsCommand($alertService);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--force' => true,
            '--email' => 'target@example.test',
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Ciblage du compte : target@example.test', $tester->getDisplay());
        self::assertStringContainsString('Mode force activé', $tester->getDisplay());
        self::assertStringContainsString('1 email(s) d’alerte envoyé(s)', $tester->getDisplay());
    }
}
