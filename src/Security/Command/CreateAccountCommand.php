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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:account:create',
    description: 'Créer un nouveau compte utilisateur ou administrateur en ligne de commande.',
)]
final class CreateAccountCommand extends Command
{
    public function __construct(
        private readonly AccountRepository $accountRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email du compte à créer')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe initial du compte')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Créer le compte avec les droits administrateur (ROLE_ADMIN)')
            ->addOption('unverified', null, InputOption::VALUE_NONE, 'Ne pas valider automatiquement l\'adresse email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $password = (string) $input->getArgument('password');
        $isAdmin = (bool) $input->getOption('admin');
        $isUnverified = (bool) $input->getOption('unverified');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error(sprintf('L\'adresse email « %s » n\'est pas valide.', $email));

            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error('Le mot de passe doit comporter au moins 8 caractères.');

            return Command::FAILURE;
        }

        $existing = $this->accountRepository->loadUserByIdentifier($email);
        if ($existing instanceof Account) {
            $io->error(sprintf('Un compte existe déjà avec l\'adresse email « %s ». Utilisez app:account:promote-admin si vous souhaitez le promouvoir.', $email));

            return Command::FAILURE;
        }

        $account = new Account($email);
        $account->setPassword($this->passwordHasher->hashPassword($account, $password));

        if (!$isUnverified) {
            $account->verifyEmail();
        }

        if ($isAdmin) {
            $account->grantAdmin();
        }

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $roleText = $isAdmin ? 'Administrateur (ROLE_ADMIN)' : 'Utilisateur standard';
        $io->success(sprintf('Le compte « %s » a été créé avec succès ! [Rôle: %s, Email vérifié: %s]', $email, $roleText, $account->isEmailVerified() ? 'Oui' : 'Non'));

        return Command::SUCCESS;
    }
}
