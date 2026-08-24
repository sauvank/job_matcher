<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Entity\Account;
use App\Security\GoogleOAuthClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountSettingsController extends AbstractController
{
    #[Route('/compte/parametres', name: 'app_account_settings', methods: ['GET'])]
    public function __invoke(#[CurrentUser] Account $account, GoogleOAuthClient $google): Response
    {
        return $this->render('account/settings.html.twig', [
            'googleEnabled' => $google->isConfigured(),
            'googleConnected' => $account->isGoogleConnected(),
        ]);
    }
}
