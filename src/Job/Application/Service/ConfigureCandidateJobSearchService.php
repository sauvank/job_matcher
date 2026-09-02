<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\ApecSearchUrlBuilder;
use App\Job\Provider\FranceTravailSearchUrlBuilder;
use App\Job\Provider\HelloWorkSearchUrlBuilder;
use App\Job\Provider\IndeedSearchUrlBuilder;
use App\Job\Provider\WelcomeToTheJungleSearchUrlBuilder;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConfigureCandidateJobSearchService
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private HelloWorkSearchUrlBuilder $helloWorkUrlBuilder,
        private IndeedSearchUrlBuilder $indeedUrlBuilder,
        private ApecSearchUrlBuilder $apecUrlBuilder,
        private FranceTravailSearchUrlBuilder $franceTravailUrlBuilder,
        private WelcomeToTheJungleSearchUrlBuilder $wttjUrlBuilder,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private SmartJobSearchQueryGenerator $queryGenerator = new SmartJobSearchQueryGenerator(),
    ) {
    }

    /** @return list<JobSource> */
    public function configure(CandidateProfile $profile): array
    {
        $title = $profile->getTitle();
        $location = $profile->getLocation();

        if ($title === null || $location === null) {
            throw new \DomainException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        $queries = $this->queryGenerator->generate($profile);
        if ($queries === []) {
            $queries = [$title];
        }

        // Auto-configure the top 3 intelligent queries based on CV skills
        $queriesToConfigure = array_slice($queries, 0, 3);
        $allSources = [];

        foreach ($queriesToConfigure as $query) {
            foreach ($this->configureAllProviders($profile, $query, $location) as $source) {
                $allSources[] = $source;
            }
        }

        return $allSources;
    }

    /** @return list<string> */
    public function getSmartQueries(CandidateProfile $profile): array
    {
        return $this->queryGenerator->generate($profile);
    }

    public function configureTitle(CandidateProfile $profile, string $title, string $location): JobSource
    {
        $sources = $this->configureAllProviders($profile, $title, $location);

        return $sources[0];
    }

    /** @return list<JobSource> */
    public function configureAllProviders(CandidateProfile $profile, string $title, string $location): array
    {
        $sources = [];
        $sources[] = $this->configureProviderSource($profile, $title, $location, JobProviderType::HELLOWORK);
        $sources[] = $this->configureProviderSource($profile, $title, $location, JobProviderType::APEC);
        $sources[] = $this->configureProviderSource($profile, $title, $location, JobProviderType::FRANCE_TRAVAIL);
        $sources[] = $this->configureProviderSource($profile, $title, $location, JobProviderType::WELCOME_TO_THE_JUNGLE);
        $sources[] = $this->configureProviderSource($profile, $title, $location, JobProviderType::INDEED);

        return $sources;
    }

    public function configureProviderSource(
        CandidateProfile $profile,
        string $title,
        string $location,
        JobProviderType $provider,
    ): JobSource {
        $title = trim($title);
        $location = trim($location);
        if ($title === '' || $location === '') {
            throw new \DomainException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        $url = match ($provider) {
            JobProviderType::HELLOWORK => $this->helloWorkUrlBuilder->build($title, $location),
            JobProviderType::APEC => $this->apecUrlBuilder->build($title, $location),
            JobProviderType::FRANCE_TRAVAIL => $this->franceTravailUrlBuilder->build($title, $location),
            JobProviderType::WELCOME_TO_THE_JUNGLE => $this->wttjUrlBuilder->build($title, $location),
            JobProviderType::INDEED => $this->indeedUrlBuilder->build($title, $location),
            default => throw new \InvalidArgumentException('Unsupported provider type: '.$provider->value),
        };

        $providerLabel = match ($provider) {
            JobProviderType::HELLOWORK => 'HelloWork',
            JobProviderType::APEC => 'Apec',
            JobProviderType::FRANCE_TRAVAIL => 'France Travail',
            JobProviderType::WELCOME_TO_THE_JUNGLE => 'Welcome to the Jungle',
            JobProviderType::INDEED => 'Indeed',
        };

        $name = mb_substr(sprintf('%s — %s — %s', $providerLabel, $title, $location), 0, 120);
        $document = $profile->getActiveCvDocument();
        $source = $this->sourceRepository->findOneByProfileAndUrl($profile, $url);

        if ($source === null) {
            $source = new JobSource($profile, $name, $url, $provider, $document);
            $this->entityManager->persist($source);
        }

        $source->queueSync();
        $this->entityManager->flush();

        $sourceId = $source->getId();
        if ($sourceId === null) {
            throw new \RuntimeException(JobMessage::SOURCE_NOT_FOUND);
        }

        $this->messageBus->dispatch(new ImportJobSourceMessage($sourceId));

        return $source;
    }
}
