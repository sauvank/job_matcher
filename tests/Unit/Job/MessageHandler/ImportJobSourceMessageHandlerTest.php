<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\MessageHandler;

use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\MessageHandler\ImportJobSourceMessageHandler;
use App\Job\Provider\FakeJobProvider;
use App\Job\Provider\JobProviderRegistry;
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
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(4))->method('flush');
        $entityManager->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(JobOffer::class))
            ->willReturnCallback(static function (JobOffer $offer) use ($offerRepository): void {
                $offerRepository->offer = $offer;
            });
        $handler = new ImportJobSourceMessageHandler(
            $sourceRepository,
            $offerRepository,
            new JobProviderRegistry([new FakeJobProvider()]),
            $entityManager,
            new LockFactory(new InMemoryStore()),
            new NullLogger(),
        );

        $handler(new ImportJobSourceMessage(12));
        $handler(new ImportJobSourceMessage(12));

        self::assertNotNull($offerRepository->offer);
        self::assertSame('fake-php-symfony', $offerRepository->offer->getExternalId());
        self::assertNotNull($source->getLastSuccessAt());
        self::assertNull($source->getLastError());
    }
}
