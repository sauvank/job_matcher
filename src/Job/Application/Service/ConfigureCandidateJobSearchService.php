<?php

declare(strict_types=1);

namespace App\Job\Application\Service;

use App\Candidate\Entity\CandidateProfile;
use App\Job\Application\Repository\JobOfferRepositoryInterface;
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
        private JobOfferRepositoryInterface $offerRepository,
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

        $url = $this->urlBuilder->build($title, $location);
        $name = mb_substr(sprintf('HelloWork — %s — %s', $title, $location), 0, 120);
        $source = $this->sourceRepository->findOneByProvider(JobProviderType::HELLOWORK);

        if ($source === null) {
            $source = new JobSource($name, $url, JobProviderType::HELLOWORK);
            $this->entityManager->persist($source);
        } elseif ($source->getUrl() !== $url) {
            $this->offerRepository->deleteBySource($source);
            $source->updateSearch($name, $url);
        }

        $this->entityManager->flush();

        $sourceId = $source->getId();
        if ($sourceId === null) {
            throw new \RuntimeException(JobMessage::SOURCE_NOT_FOUND);
        }

        $this->messageBus->dispatch(new ImportJobSourceMessage($sourceId));

        return $source;
    }
}
