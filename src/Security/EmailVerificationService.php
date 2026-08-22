<?php

declare(strict_types=1);

namespace App\Security;

use App\Security\Entity\Account;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class EmailVerificationService
{
    private UriSigner $signer;

    public function __construct(
        private MailerInterface $mailer,
        private RouterInterface $router,
        string $appSecret,
        private string $sender,
        private int $verificationLifetimeSeconds,
    ) {
        $this->signer = new UriSigner($appSecret);
    }

    public function send(Account $account): void
    {
        $accountId = $account->getId();
        if ($accountId === null) {
            throw new \LogicException('The account must be persisted before sending its verification email.');
        }

        $url = $this->router->generate('app_email_verify', ['id' => $accountId], UrlGeneratorInterface::ABSOLUTE_URL);
        $signedUrl = $this->signer->sign($url, time() + $this->verificationLifetimeSeconds);
        $email = (new Email())
            ->from($this->sender)
            ->to($account->getEmail())
            ->subject('Vérifiez votre adresse email — Job Matcher')
            ->text(<<<TEXT
                Bienvenue sur Job Matcher.

                Vérifiez votre adresse email en ouvrant ce lien dans les 24 heures :
                {$signedUrl}

                Si vous n'avez pas créé ce compte, ignorez cet email.
                TEXT);

        $this->mailer->send($email);
    }

    public function isValid(Request $request): bool
    {
        return $this->signer->checkRequest($request);
    }
}
