<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\HelloWorkSearchUrlBuilder;
use App\Job\Provider\IndeedSearchUrlBuilder;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConfigureCandidateJobSearchService
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private HelloWorkSearchUrlBuilder $helloWorkUrlBuilder,
        private IndeedSearchUrlBuilder $indeedUrlBuilder,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
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

        return $this->configureAllProviders($profile, $title, $location);
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
            JobProviderType::INDEED => $this->indeedUrlBuilder->build($title, $location),
            JobProviderType::FAKE => throw new \InvalidArgumentException('Unsupported provider type: '.$provider->value),
        };

        $providerLabel = match ($provider) {
            JobProviderType::HELLOWORK => 'HelloWork',
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
