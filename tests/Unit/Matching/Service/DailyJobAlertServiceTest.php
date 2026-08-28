<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Service;

use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use App\Matching\Service\DailyJobAlertService;
use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class DailyJobAlertServiceTest extends TestCase
{
    public function testItSendsAlertWhenMatchingOffersFound(): void
    {
        $account = new Account('candidate@example.test');
        $account->setAlertScoreThreshold(75);

        /** @var AccountRepository&MockObject $accountRepository */
        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('findAccountsForDailyAlerts')->willReturn([$account]);

        $profile = $account->getCandidateProfile();
        $source = new JobSource($profile, 'Search', 'https://example.test', JobProviderType::FAKE);
        $offer = new JobOffer($source, new NormalizedJobOffer(
            externalId: 'ext-1',
            url: 'https://example.test/1',
            title: 'Dev PHP Symfony',
            company: 'Acme Corp',
            location: 'Paris',
            contractType: 'CDI',
            minimumSalary: 50000,
            maximumSalary: 60000,
            remotePolicy: 'HYBRID',
            yearsOfExperience: 3,
            description: 'Offre PHP Symfony',
            publishedAt: null,
            validThrough: null,
            rawPayload: [],
        ));
        $match = new JobMatch($profile, $offer, new MatchScore(80, 80, 80, 80, 80, 80, 80, 80, 80, [], [], [], []));

        /** @var JobMatchRepositoryInterface&MockObject $matchRepository */
        $matchRepository = $this->createMock(JobMatchRepositoryInterface::class);
        $matchRepository->expects(self::once())
            ->method('findMatchesForDailyAlert')
            ->with($profile, 75, self::isInstanceOf(\DateTimeImmutable::class), 15)
            ->willReturn([$match]);

        /** @var MailerInterface&MockObject $mailer */
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email) use ($account): bool {
                $toAddresses = array_map(static fn ($addr) => $addr->getAddress(), $email->getTo());
                self::assertContains($account->getEmail(), $toAddresses);
                $subject = (string) $email->getSubject();
                self::assertStringContainsString('1 nouvelle offre compatible (≥ 75%)', $subject);

                return true;
            }));

        /** @var Environment&MockObject $twig */
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::exactly(2))
            ->method('render')
            ->willReturn('<html>content</html>', 'plain content');

        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new DailyJobAlertService(
            $accountRepository,
            $matchRepository,
            $mailer,
            $twig,
            $entityManager,
            'job-matcher@example.test'
        );

        $now = new \DateTimeImmutable('2026-08-28 08:00:00');
        $sentCount = $service->sendDailyAlerts($now);

        self::assertSame(1, $sentCount);
        self::assertSame($now, $account->getLastAlertEmailSentAt());
    }

    public function testItDoesNotSendEmailWhenNoMatchesFound(): void
    {
        $account = new Account('candidate@example.test');

        /** @var AccountRepository&MockObject $accountRepository */
        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('findAccountsForDailyAlerts')->willReturn([$account]);

        /** @var JobMatchRepositoryInterface&MockObject $matchRepository */
        $matchRepository = $this->createMock(JobMatchRepositoryInterface::class);
        $matchRepository->method('findMatchesForDailyAlert')->willReturn([]);

        /** @var MailerInterface&MockObject $mailer */
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        /** @var Environment&MockObject $twig */
        $twig = $this->createMock(Environment::class);
        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new DailyJobAlertService(
            $accountRepository,
            $matchRepository,
            $mailer,
            $twig,
            $entityManager,
            'job-matcher@example.test'
        );

        $sentCount = $service->sendDailyAlerts();

        self::assertSame(0, $sentCount);
        self::assertNull($account->getLastAlertEmailSentAt());
    }

    public function testItSkipsDisabledAccountUnlessForced(): void
    {
        $account = new Account('disabled@example.test');
        $account->setAlertEmailEnabled(false);

        /** @var AccountRepository&MockObject $accountRepository */
        $accountRepository = $this->createMock(AccountRepository::class);
        $accountRepository->method('loadUserByIdentifier')->willReturn($account);

        /** @var JobMatchRepositoryInterface&MockObject $matchRepository */
        $matchRepository = $this->createMock(JobMatchRepositoryInterface::class);
        $matchRepository->expects(self::never())->method('findMatchesForDailyAlert');

        /** @var MailerInterface&MockObject $mailer */
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        /** @var Environment&MockObject $twig */
        $twig = $this->createMock(Environment::class);
        /** @var EntityManagerInterface&MockObject $entityManager */
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new DailyJobAlertService(
            $accountRepository,
            $matchRepository,
            $mailer,
            $twig,
            $entityManager,
            'job-matcher@example.test'
        );

        $sentCount = $service->sendDailyAlerts(targetEmail: 'disabled@example.test');
        self::assertSame(0, $sentCount);
    }
}
