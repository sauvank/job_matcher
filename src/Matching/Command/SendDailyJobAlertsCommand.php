<?php

declare(strict_types=1);

namespace App\Matching\Command;

use App\Matching\Service\DailyJobAlertService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:alerts:send-daily', description: 'Envoie les alertes email quotidiennes pour les nouvelles offres compatibles.')]
final class SendDailyJobAlertsCommand extends Command
{
    public function __construct(
        private readonly DailyJobAlertService $alertService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force l’envoi sans tenir compte du dernier envoi (prend les 7 derniers jours)')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Cibler uniquement une adresse email spécifique');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $email = $input->getOption('email');
        $targetEmail = is_string($email) && trim($email) !== '' ? trim($email) : null;

        $io->title('Envoi des alertes email quotidiennes');

        if ($targetEmail !== null) {
            $io->note(sprintf('Ciblage du compte : %s', $targetEmail));
        }

        if ($force) {
            $io->warning('Mode force activé : historique de 7 jours pris en compte.');
        }

        $sentCount = $this->alertService->sendDailyAlerts(
            force: $force,
            targetEmail: $targetEmail
        );

        $io->success(sprintf('%d email(s) d’alerte envoyé(s) avec succès.', $sentCount));

        return Command::SUCCESS;
    }
}
