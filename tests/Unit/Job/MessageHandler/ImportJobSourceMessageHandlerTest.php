<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\MessageHandler;

use App\Candidate\Application\Repository\CandidateProfileRepositoryInterface;
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
use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use App\Matching\Service\DeterministicJobScorer;
use App\Matching\Service\MatchJobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class ImportJobSourceMessageHandlerTest extends TestCase
{
    public function testItImportsAJobOfferIdempotently(): void
    {
        $source = new JobSource('Fake', 'https://example.test/jobs', JobProviderType::FAKE);
        $sourceRepository = new class($source) implements JobSourceRepositoryInterface {
            public function __construct(private readonly JobSource $source)
            {
            }

            public function get(int $id): ?JobSource
            {
                return $id === 12 ? $this->source : null;
            }

            public function findOneByProvider(JobProviderType $provider): ?JobSource
            {
                return $this->source->getProvider() === $provider ? $this->source : null;
            }
        };
        $offerRepository = new class implements JobOfferRepositoryInterface {
            public ?JobOffer $offer = null;

            public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer
            {
                return $this->offer;
            }

            public function deleteBySource(JobSource $source): void
            {
            }
        };
        $profile = new CandidateProfile();
        $profileRepository = new class($profile) implements CandidateProfileRepositoryInterface {
            public function __construct(private readonly CandidateProfile $profile)
            {
            }

            public function findDefault(): CandidateProfile
            {
                return $this->profile;
            }
        };
        $matchRepository = new class implements JobMatchRepositoryInterface {
            public ?JobMatch $match = null;

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
                }
            });
        $matchService = new MatchJobOfferService(
            $matchRepository,
            new DeterministicJobScorer([
                'stack' => 35,
                'experience' => 15,
                'salary' => 15,
                'location' => 10,
                'contract' => 10,
                'remote' => 5,
                'backend' => 10,
            ]),
            $entityManager,
        );
        $handler = new ImportJobSourceMessageHandler(
            $sourceRepository,
            $offerRepository,
            new JobProviderRegistry([new FakeJobProvider()]),
            $profileRepository,
            $matchService,
            $entityManager,
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
