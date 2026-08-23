<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
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
    public function testItCreatesOneSourcePerSearchTitle(): void
    {
        $sourceRepository = new class implements JobSourceRepositoryInterface {
            /** @var list<JobSource> */
            public array $sources = [];

            public function get(int $id): ?JobSource
            {
                return null;
            }

            public function findOneByProfileAndUrl(CandidateProfile $profile, string $url): ?JobSource
            {
                foreach ($this->sources as $source) {
                    if ($source->getCandidateProfile() === $profile && $source->getUrl() === $url) {
                        return $source;
                    }
                }

                return null;
            }

            public function findEnabled(): array
            {
                return array_values(array_filter($this->sources, static fn (JobSource $source): bool => $source->isEnabled()));
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (JobSource $source) use ($sourceRepository): void {
                $sourceRepository->sources[] = $source;
                (new \ReflectionProperty($source, 'id'))->setValue($source, count($sourceRepository->sources));
            });
        $entityManager->expects(self::exactly(2))->method('flush');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $service = new ConfigureCandidateJobSearchService(
            $sourceRepository,
            new HelloWorkSearchUrlBuilder(),
            $entityManager,
            $messageBus,
        );

        $profile = new CandidateProfile();
        $phpSource = $service->configureTitle($profile, 'Développeur PHP backend', 'Lyon');
        $symfonySource = $service->configureTitle($profile, 'Symfony', 'Lyon');

        self::assertNotSame($phpSource, $symfonySource);
        self::assertCount(2, $sourceRepository->sources);
        self::assertStringContainsString('k=D%C3%A9veloppeur+PHP+backend', $phpSource->getUrl());
        self::assertStringContainsString('k=Symfony', $symfonySource->getUrl());
    }

    public function testItReusesAnExistingSearchForTheCandidateProfile(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur PHP', 'Paris', 5, 'Contenu du CV');

        $urlBuilder = new HelloWorkSearchUrlBuilder();
        $source = new JobSource(
            $profile,
            'HelloWork — Développeur PHP — Paris',
            $urlBuilder->build('Développeur PHP', 'Paris'),
            JobProviderType::HELLOWORK,
        );
        (new \ReflectionProperty($source, 'id'))->setValue($source, 12);

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
                return $profile === $this->source->getCandidateProfile() && $url === $this->source->getUrl() ? $this->source : null;
            }

            public function findEnabled(): array
            {
                return $this->source->isEnabled() ? [$this->source] : [];
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
            $urlBuilder,
            $entityManager,
            $messageBus,
        ))->configure($profile);

        self::assertSame($source, $configuredSource);
        self::assertSame('HelloWork — Développeur PHP — Paris', $source->getName());
        self::assertStringContainsString('k=D%C3%A9veloppeur+PHP', $source->getUrl());
        self::assertStringContainsString('l=Paris', $source->getUrl());
    }
}
