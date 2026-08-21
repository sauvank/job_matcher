<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Entity\JobSource;
use App\Job\Enum\JobProviderType;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Provider\HelloWorkSearchUrlBuilder;
use App\Job\Translation\JobMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConfigureCandidateJobSearchService
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private HelloWorkSearchUrlBuilder $urlBuilder,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function configure(CandidateProfile $profile): JobSource
    {
        $title = $profile->getTitle();
        $location = $profile->getLocation();

        if ($title === null || $location === null) {
            throw new \DomainException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        return $this->configureTitle($title, $location);
    }

    public function configureTitle(string $title, string $location): JobSource
    {
        $title = trim($title);
        $location = trim($location);
        if ($title === '' || $location === '') {
            throw new \DomainException(JobMessage::SEARCH_CRITERIA_REQUIRED);
        }

        $url = $this->urlBuilder->build($title, $location);
        $name = mb_substr(sprintf('HelloWork — %s — %s', $title, $location), 0, 120);
        $source = $this->sourceRepository->findOneByUrl($url);

        if ($source === null) {
            $source = new JobSource($name, $url, JobProviderType::HELLOWORK);
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
