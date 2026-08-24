<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\MessageHandler;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Message\RefreshJobSourcesMessage;
use App\Job\MessageHandler\RefreshJobSourcesMessageHandler;
use App\Job\Service\SchedulerExecutionTracker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RefreshJobSourcesMessageHandlerTest extends TestCase
{
    public function testItQueuesEveryEnabledSourceThatIsNotAlreadySynchronizing(): void
    {
        $readySource = $this->source(12);
        $pendingSource = $this->source(13);
        $pendingSource->queueSync();
        $repository = new class([$readySource, $pendingSource]) implements JobSourceRepositoryInterface {
            /** @param list<JobSource> $sources */
            public function __construct(private readonly array $sources)
            {
            }

            public function get(int $id): ?JobSource
            {
                return null;
            }

            public function findOneByProfileAndUrl(CandidateProfile $profile, string $url): ?JobSource
            {
                return null;
            }

            public function findEnabled(): array
            {
                return $this->sources;
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(3))->method('flush');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportJobSourceMessage && $message->jobSourceId === 12))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $tracker = new SchedulerExecutionTracker($entityManager);

        (new RefreshJobSourcesMessageHandler($repository, $entityManager, $messageBus, $tracker))(new RefreshJobSourcesMessage());

        self::assertTrue($readySource->isSyncPending());
    }

    private function source(int $id): JobSource
    {
        $source = new JobSource(new CandidateProfile(), 'Source '.$id, 'https://example.test/'.$id, JobProviderType::FAKE);
        (new \ReflectionProperty($source, 'id'))->setValue($source, $id);

        return $source;
    }
}
