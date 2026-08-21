<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobOfferRepositoryInterface;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Job\Entity\JobOffer;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\HelloWorkSearchUrlBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ConfigureCandidateJobSearchServiceTest extends TestCase
{
    public function testItUpdatesTheHelloWorkSourceFromTheCandidateProfile(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur PHP', 'Paris', 5, 'Contenu du CV');

        $source = new JobSource('Ancienne recherche', 'https://example.test/old', JobProviderType::HELLOWORK);
        (new \ReflectionProperty($source, 'id'))->setValue($source, 12);

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
                return $provider === JobProviderType::HELLOWORK ? $this->source : null;
            }
        };
        $offerRepository = new class implements JobOfferRepositoryInterface {
            public bool $deleted = false;

            public function findOneBySourceAndExternalId(JobSource $source, string $externalId): ?JobOffer
            {
                return null;
            }

            public function deleteBySource(JobSource $source): void
            {
                $this->deleted = true;
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static fn (object $message): bool => $message instanceof ImportJobSourceMessage && $message->jobSourceId === 12))
            ->willReturn(new Envelope(new ImportJobSourceMessage(12)));

        $configuredSource = (new ConfigureCandidateJobSearchService(
            $sourceRepository,
            $offerRepository,
            new HelloWorkSearchUrlBuilder(),
            $entityManager,
            $messageBus,
        ))->configure($profile);

        self::assertSame($source, $configuredSource);
        self::assertTrue($offerRepository->deleted);
        self::assertSame('HelloWork — Développeur PHP — Paris', $source->getName());
        self::assertStringContainsString('k=D%C3%A9veloppeur+PHP', $source->getUrl());
        self::assertStringContainsString('l=Paris', $source->getUrl());
    }
}
