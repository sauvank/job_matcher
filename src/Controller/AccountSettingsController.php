<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\AccountAlertSettingsType;
use App\Security\DTO\AccountAlertSettingsData;
use App\Security\Entity\Account;
use App\Security\GoogleOAuthClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountSettingsController extends AbstractController
{
    #[Route('/compte/parametres', name: 'app_account_settings', methods: ['GET', 'POST'])]
    public function __invoke(
        #[CurrentUser] Account $account,
        GoogleOAuthClient $google,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $alertData = AccountAlertSettingsData::fromAccount($account);
        $alertForm = $this->createForm(AccountAlertSettingsType::class, $alertData);
        $alertForm->handleRequest($request);

        if ($alertForm->isSubmitted() && $alertForm->isValid()) {
            $account->setAlertEmailEnabled($alertData->alertEmailEnabled);
            $account->setAlertScoreThreshold($alertData->alertScoreThreshold);
            $entityManager->flush();

            $this->addFlash('success', 'Vos préférences d’alertes email ont été enregistrées.');

            return $this->redirectToRoute('app_account_settings');
        }

        return $this->render('account/settings.html.twig', [
            'googleEnabled' => $google->isConfigured(),
            'googleConnected' => $account->isGoogleConnected(),
            'alertForm' => $alertForm->createView(),
            'account' => $account,
        ]);
    }
}
