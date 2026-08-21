<?php

declare(strict_types=1);

namespace App\Matching\Command;

use App\Matching\Message\AnalyzeJobMatchMessage;
use App\Matching\Repository\JobMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:matches:analyze', description: 'Queue semantic compatibility analyses for existing job matches.')]
final class AnalyzeJobMatchesCommand extends Command
{
    public function __construct(
        private readonly JobMatchRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('ids', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Identifiers of the matches to analyze.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $input->getArgument('ids')), static fn (int $id): bool => $id > 0));
        $matches = $ids === []
            ? $this->repository->findBy(['semanticAnalysisStatus' => 'NOT_REQUESTED'])
            : $this->repository->findBy(['id' => $ids]);

        $count = 0;
        foreach ($matches as $match) {
            $id = $match->getId();
            if ($id === null) {
                continue;
            }
            $match->queueSemanticAnalysis();
            $this->messageBus->dispatch(new AnalyzeJobMatchMessage($id));
            ++$count;
        }
        $this->entityManager->flush();
        $output->writeln(sprintf('<info>%d analyse(s) sémantique(s) mise(s) en attente.</info>', $count));

        return Command::SUCCESS;
    }
}
