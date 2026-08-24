<?php

declare(strict_types=1);

namespace App\Security\Command;

use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:account:promote-admin',
    description: 'Promouvoir ou rétrograder un compte utilisateur en tant qu\'administrateur.',
)]
final class PromoteAdminCommand extends Command
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email du compte à promouvoir')
            ->addOption('demote', null, InputOption::VALUE_NONE, 'Retirer les droits administrateur au lieu de les accorder')
            ->addOption('no-verify', null, InputOption::VALUE_NONE, 'Ne pas marquer automatiquement l\'email comme vérifié');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $isDemote = (bool) $input->getOption('demote');
        $noVerify = (bool) $input->getOption('no-verify');

        $account = $this->accountRepository->loadUserByIdentifier($email);
        if (!$account instanceof Account) {
            $io->error(sprintf('Aucun compte trouvé avec l\'adresse email « %s ».', $email));

            return Command::FAILURE;
        }

        if ($isDemote) {
            $account->revokeAdmin();
            $this->entityManager->flush();
            $io->success(sprintf('Les privilèges administrateur ont été retirés pour « %s ».', $email));

            return Command::SUCCESS;
        }

        $account->grantAdmin();
        if (!$noVerify && !$account->isEmailVerified()) {
            $account->verifyEmail();
        }

        $this->entityManager->flush();

        $io->success(sprintf('Le compte « %s » est désormais administrateur (%s).', $email, implode(', ', $account->getRoles())));

        return Command::SUCCESS;
    }
}
