<?php

declare(strict_types=1);

namespace App\Matching\Command;

use App\Candidate\Application\Repository\CandidateProfileRepositoryInterface;
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
        private readonly CandidateProfileRepositoryInterface $profileRepository,
        private readonly JobOfferRepository $offerRepository,
        private readonly MatchJobOfferService $matchService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $this->profileRepository->findDefault();
        if ($profile === null) {
            $output->writeln('<error>Aucun profil candidat disponible.</error>');

            return Command::FAILURE;
        }

        $count = 0;
        foreach ($this->offerRepository->findRecent(1000) as $offer) {
            $this->matchService->match($profile, $offer);
            ++$count;
        }

        $this->entityManager->flush();
        $output->writeln(sprintf('<info>%d offre(s) évaluée(s).</info>', $count));

        return Command::SUCCESS;
    }
}
