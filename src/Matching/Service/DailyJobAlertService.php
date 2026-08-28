<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Matching\Application\Repository\JobMatchRepositoryInterface;
use App\Matching\Entity\JobMatch;
use App\Security\Entity\Account;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

readonly class DailyJobAlertService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private JobMatchRepositoryInterface $matchRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private EntityManagerInterface $entityManager,
        private string $sender,
    ) {
    }

    /**
     * @return int Number of alert emails sent
     */
    public function sendDailyAlerts(?\DateTimeImmutable $now = null, bool $force = false, ?string $targetEmail = null): int
    {
        $now ??= new \DateTimeImmutable();
        $accounts = $this->getTargetAccounts($targetEmail);
        $sentCount = 0;

        foreach ($accounts as $account) {
            if (!$force && !$account->isAlertEmailEnabled()) {
                continue;
            }

            $threshold = $account->getAlertScoreThreshold();
            $since = $this->resolveSinceTimestamp($account, $now, $force);

            $matches = $this->matchRepository->findMatchesForDailyAlert(
                $account->getCandidateProfile(),
                $threshold,
                $since,
                limit: 15
            );

            if ($matches === []) {
                continue;
            }

            $this->sendAlertEmail($account, $matches, $threshold);
            $account->setLastAlertEmailSentAt($now);
            $this->entityManager->flush();
            ++$sentCount;
        }

        return $sentCount;
    }

    /**
     * @return list<Account>
     */
    private function getTargetAccounts(?string $targetEmail): array
    {
        if ($targetEmail !== null && trim($targetEmail) !== '') {
            $account = $this->accountRepository->loadUserByIdentifier($targetEmail);

            return $account !== null ? [$account] : [];
        }

        return $this->accountRepository->findAccountsForDailyAlerts();
    }

    private function resolveSinceTimestamp(Account $account, \DateTimeImmutable $now, bool $force): \DateTimeImmutable
    {
        if ($force) {
            return $now->modify('-7 days');
        }

        $lastSent = $account->getLastAlertEmailSentAt();
        if ($lastSent !== null) {
            return $lastSent;
        }

        return $now->modify('-24 hours');
    }

    /**
     * @param list<JobMatch> $matches
     */
    private function sendAlertEmail(Account $account, array $matches, int $threshold): void
    {
        $count = count($matches);
        $subject = sprintf(
            '🎯 [Job Matcher] %d nouvelle%s offre%s compatible%s (≥ %d%%)',
            $count,
            $count > 1 ? 's' : '',
            $count > 1 ? 's' : '',
            $count > 1 ? 's' : '',
            $threshold
        );

        $htmlBody = $this->twig->render('emails/daily_job_alert.html.twig', [
            'account' => $account,
            'matches' => $matches,
            'threshold' => $threshold,
        ]);

        $textBody = $this->twig->render('emails/daily_job_alert.txt.twig', [
            'account' => $account,
            'matches' => $matches,
            'threshold' => $threshold,
        ]);

        $email = (new Email())
            ->from($this->sender)
            ->to($account->getEmail())
            ->subject($subject)
            ->html($htmlBody)
            ->text($textBody);

        $this->mailer->send($email);
    }
}
