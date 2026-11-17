<?php

declare(strict_types=1);

namespace App\Matching\Service;

use App\Security\Repository\AccountRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Warns administrators by email when the AI provider refuses requests because
 * the account has no credit left. At most one email per provider and period.
 */
readonly class AiCreditAlertNotifier
{
    private const THROTTLE_SECONDS = 86400;

    public function __construct(
        private AccountRepository $accountRepository,
        private MailerInterface $mailer,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private string $sender,
    ) {
    }

    public function notifyCreditExhausted(string $provider): bool
    {
        try {
            $item = $this->cache->getItem('ai_credit_exhausted_alert_'.strtolower($provider));
            if ($item->isHit()) {
                return false;
            }

            $recipients = array_map(
                static fn ($account): string => $account->getEmail(),
                $this->accountRepository->findAdmins(),
            );
            if ($recipients === []) {
                $this->logger->warning('ai.credit_alert.no_admin', ['provider' => $provider]);

                return false;
            }

            $email = (new Email())
                ->from($this->sender)
                ->to(...$recipients)
                ->subject(sprintf('⚠️ [Job Matcher] Crédit IA %s épuisé', $provider))
                ->text(sprintf(
                    "Le fournisseur IA %s refuse les requêtes : le crédit du compte API est épuisé.\n\n"
                    ."Les analyses d'offres et de CV échouent tant que le crédit n'est pas rechargé.\n"
                    ."Après la recharge, relancez les analyses en échec (bouton « Relancer l'analyse » ou commande app:matches:analyze).\n\n"
                    .'Cette alerte est envoyée au plus une fois toutes les 24 heures.',
                    $provider,
                ));

            $this->mailer->send($email);
            $item->set(true)->expiresAfter(self::THROTTLE_SECONDS);
            $this->cache->save($item);
            $this->logger->warning('ai.credit_alert.sent', ['provider' => $provider, 'recipientCount' => count($recipients)]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('ai.credit_alert.failed', ['provider' => $provider, 'exception' => $exception]);

            return false;
        }
    }
}
