<?php

declare(strict_types=1);

namespace App\Tests\Unit\Matching\Command;

use App\Candidate\Entity\CandidateProfile;
use App\Job\DTO\NormalizedJobOffer;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Command\AnalyzeJobMatchesCommand;
use App\Matching\DTO\MatchScore;
use App\Matching\Entity\JobMatch;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AnalyzeJobMatchesCommandTest extends TestCase
{
    public function testExecuteQueuesMatchesAndSupportsRecoverOption(): void
    {
        $profile = new CandidateProfile();
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
        (new \ReflectionProperty($match, 'id'))->setValue($match, 42);

        $repository = $this->createMock(JobMatchRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('recoverAllStuckAnalyses')
            ->willReturn(2);
        $repository->expects(self::once())
            ->method('findPendingSemanticAnalyses')
            ->with([])
            ->willReturn([$match]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $command = new AnalyzeJobMatchesCommand($repository, $entityManager, $messageBus);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--recover' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('2 analyse(s) bloquée(s) récupérée(s).', $tester->getDisplay());
        self::assertStringContainsString('1 analyse(s) sémantique(s) mise(s) en attente.', $tester->getDisplay());
    }
}
