<?php

declare(strict_types=1);

namespace App\Job\MessageHandler;

use App\Job\Application\Repository\JobSourceRepositoryInterface;
use App\Job\Message\ImportJobSourceMessage;
use App\Job\Message\RefreshJobSourcesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RefreshJobSourcesMessageHandler
{
    public function __construct(
        private JobSourceRepositoryInterface $sourceRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(RefreshJobSourcesMessage $message): void
    {
        foreach ($this->sourceRepository->findEnabled() as $source) {
            if ($source->isSyncPending()) {
                continue;
            }

            $source->queueSync();
            $this->entityManager->flush();

            $sourceId = $source->getId();
            if ($sourceId === null) {
                throw new \LogicException('A persisted job source must have an identifier.');
            }

            $this->messageBus->dispatch(new ImportJobSourceMessage($sourceId));
        }
    }
}
