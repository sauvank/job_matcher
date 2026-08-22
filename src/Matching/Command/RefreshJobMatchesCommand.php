<?php

declare(strict_types=1);

namespace App\Matching\Command;

use App\Job\Repository\JobOfferRepository;
use App\Matching\Service\MatchJobOfferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:matches:refresh', description: 'Recalculate deterministic compatibility scores for imported offers.')]
final class RefreshJobMatchesCommand extends Command
{
    public function __construct(
        private readonly JobOfferRepository $offerRepository,
        private readonly MatchJobOfferService $matchService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        foreach ($this->offerRepository->findRecent(1000) as $offer) {
            $this->matchService->match($offer->getSource()->getCandidateProfile(), $offer);
            ++$count;
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('<info>%d offre(s) évaluée(s).</info>', $count));

        return Command::SUCCESS;
    }
}
