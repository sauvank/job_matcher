<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\MessageHandler;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\MessageHandler\ImportJobSourceMessageHandler;
use App\Job\Provider\FakeJobProvider;
use App\Job\Provider\JobProviderRegistry;
use App\Job\Service\TechnicalRequirementExtractor;
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Service\DeterministicJobScorer;
use App\Matching\Service\MatchJobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportJobSourceMessageHandlerTest extends TestCase
{
    public function testItImportsAJobOfferIdempotently(): void
    {
        $profile = new CandidateProfile();
        $source = new JobSource($profile, 'Fake', 'https://example.test/jobs', JobProviderType::FAKE);
        $sourceRepository = new class($source) implements JobSourceRepositoryInterface {
            public function __construct(private readonly JobSource $source)
            {
            }

            public function get(int $id): ?JobSource
            {
                return $id === 12 ? $this->source : null;
            }

            public function findOneByProfileAndUrl(CandidateProfile $profile, string $url): ?JobSource
            {
                return $this->source->getCandidateProfile() === $profile && $this->source->getUrl() === $url ? $this->source : null;
            }

            public function findEnabled(): array
            {
                return $this->source->isEnabled() ? [$this->source] : [];
            }
        };
        $offerRepository = new class implements JobOfferRepositoryInterface {
            public ?JobOffer $offer = null;

            public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer
            {
                return $this->offer;
            }

            public function findActiveBySource(JobSource $source): array
            {
                return $this->offer === null ? [] : [$this->offer];
            }

            public function deleteBySource(JobSource $source): void
            {
            }
        };
        $matchRepository = new class implements JobMatchRepositoryInterface {
            public ?JobMatch $match = null;

            public function get(int $id): ?JobMatch
            {
                return $this->match;
            }

            public function findOneFor(CandidateProfile $profile, JobOffer $offer): ?JobMatch
            {
                return $this->match;
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(6))->method('flush');
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use ($offerRepository, $matchRepository): void {
                if ($entity instanceof JobOffer) {
                    $offerRepository->offer = $entity;
                    (new \ReflectionProperty(JobOffer::class, 'id'))->setValue($entity, 42);
                }

                if ($entity instanceof JobMatch) {
                    $matchRepository->match = $entity;
                    (new \ReflectionProperty(JobMatch::class, 'id'))->setValue($entity, 24);
                }
            });
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof AnalyzeJobMatchMessage && $message->jobMatchId === 24))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $matchService = new MatchJobOfferService(
            $matchRepository,
            new DeterministicJobScorer(
                [
                    'stack' => 35,
                    'experience' => 15,
                    'salary' => 15,
                    'location' => 10,
                    'contract' => 10,
                    'remote' => 5,
                    'backend' => 10,
                ],
                new TechnicalRequirementExtractor(),
            ),
            $entityManager,
        );
        $handler = new ImportJobSourceMessageHandler(
            $sourceRepository,
            $offerRepository,
            new JobProviderRegistry([new FakeJobProvider()]),
            $matchService,
            $entityManager,
            $messageBus,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        $handler(new ImportJobSourceMessage(12));
        $handler(new ImportJobSourceMessage(12));

        self::assertNotNull($offerRepository->offer);
        self::assertNotNull($matchRepository->match);
        self::assertSame('fake-php-symfony', $offerRepository->offer->getExternalId());
        self::assertNotNull($source->getLastSuccessAt());
        self::assertNull($source->getLastError());
        self::assertSame(1, $source->getProcessedOfferCount());
    }
}
