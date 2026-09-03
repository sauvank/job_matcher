<?php

declare(strict_types=1);

namespace App\Tests\Unit\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Application\Service\ConfigureCandidateJobSearchService;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Provider\ApecSearchUrlBuilder;
use App\Job\Provider\FranceTravailSearchUrlBuilder;
use App\Job\Provider\FreeWorkSearchUrlBuilder;
use App\Job\Provider\HelloWorkSearchUrlBuilder;
use App\Job\Provider\IndeedSearchUrlBuilder;
use App\Job\Provider\WelcomeToTheJungleSearchUrlBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ConfigureCandidateJobSearchServiceTest extends TestCase
{
    public function testItCreatesSourcesForAllConfiguredProvidersPerSearchTitle(): void
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

            public function recoverStuckSyncsForProfile(CandidateProfile $profile, int $timeoutMinutes = 10): int
            {
                return 0;
            }

            public function recoverAllStuckSyncs(int $timeoutMinutes = 10): int
            {
                return 0;
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(12))
            ->method('persist')
            ->willReturnCallback(static function (JobSource $source) use ($sourceRepository): void {
                $sourceRepository->sources[] = $source;
                (new \ReflectionProperty($source, 'id'))->setValue($source, count($sourceRepository->sources));
            });
        $entityManager->expects(self::exactly(12))->method('flush');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(12))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $service = new ConfigureCandidateJobSearchService(
            $sourceRepository,
            new HelloWorkSearchUrlBuilder(),
            new IndeedSearchUrlBuilder(),
            new ApecSearchUrlBuilder(),
            new FranceTravailSearchUrlBuilder(),
            new WelcomeToTheJungleSearchUrlBuilder(),
            new FreeWorkSearchUrlBuilder(),
            $entityManager,
            $messageBus,
        );

        $profile = new CandidateProfile();
        $phpSource = $service->configureTitle($profile, 'Développeur PHP backend', 'Lyon');
        $symfonySource = $service->configureTitle($profile, 'Symfony', 'Lyon');

        self::assertNotSame($phpSource, $symfonySource);
        self::assertCount(12, $sourceRepository->sources);
        self::assertSame(JobProviderType::HELLOWORK, $sourceRepository->sources[0]->getProvider());
        self::assertSame(JobProviderType::APEC, $sourceRepository->sources[1]->getProvider());
        self::assertSame(JobProviderType::FRANCE_TRAVAIL, $sourceRepository->sources[2]->getProvider());
        self::assertSame(JobProviderType::WELCOME_TO_THE_JUNGLE, $sourceRepository->sources[3]->getProvider());
        self::assertSame(JobProviderType::FREE_WORK, $sourceRepository->sources[4]->getProvider());
        self::assertSame(JobProviderType::INDEED, $sourceRepository->sources[5]->getProvider());
    }

    public function testItReusesAnExistingSearchForTheCandidateProfile(): void
    {
        $profile = new CandidateProfile();
        $profile->updateFromCv('Développeur PHP', 'Paris', 5, 'Contenu du CV');

        $helloWorkUrlBuilder = new HelloWorkSearchUrlBuilder();
        $indeedUrlBuilder = new IndeedSearchUrlBuilder();
        $apecUrlBuilder = new ApecSearchUrlBuilder();
        $franceTravailUrlBuilder = new FranceTravailSearchUrlBuilder();
        $wttjUrlBuilder = new WelcomeToTheJungleSearchUrlBuilder();
        $freeWorkUrlBuilder = new FreeWorkSearchUrlBuilder();

        $source = new JobSource(
            $profile,
            'HelloWork — Développeur PHP — Paris',
            $helloWorkUrlBuilder->build('Développeur PHP', 'Paris'),
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

            public function recoverStuckSyncsForProfile(CandidateProfile $profile, int $timeoutMinutes = 10): int
            {
                return 0;
            }

            public function recoverAllStuckSyncs(int $timeoutMinutes = 10): int
            {
                return 0;
            }
        };
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(5))
            ->method('persist')
            ->willReturnCallback(static function (JobSource $newSource): void {
                (new \ReflectionProperty($newSource, 'id'))->setValue($newSource, 13);
            });
        $entityManager->expects(self::exactly(6))->method('flush');
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::exactly(6))
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $configuredSources = (new ConfigureCandidateJobSearchService(
            $sourceRepository,
            $helloWorkUrlBuilder,
            $indeedUrlBuilder,
            $apecUrlBuilder,
            $franceTravailUrlBuilder,
            $wttjUrlBuilder,
            $freeWorkUrlBuilder,
            $entityManager,
            $messageBus,
        ))->configure($profile);

        self::assertCount(6, $configuredSources);
        self::assertSame($source, $configuredSources[0]);
        self::assertSame('HelloWork — Développeur PHP — Paris', $source->getName());
        self::assertStringContainsString('k=D%C3%A9veloppeur+PHP', $source->getUrl());
        self::assertStringContainsString('l=Paris', $source->getUrl());
    }
}
