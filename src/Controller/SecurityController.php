<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\RegistrationType;
use App\Security\DTO\RegistrationData;
use App\Security\Entity\Account;
use App\Security\GoogleOAuthClient;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, GoogleOAuthClient $google): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_candidate_profile');
        }

        return $this->render('security/login.html.twig', [
            'lastEmail' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'googleEnabled' => $google->isConfigured(),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        AccountRepository $accounts,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        Security $security,
        GoogleOAuthClient $google,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_candidate_profile');
        }
        if ($accounts->count([]) > 0) {
            $this->addFlash('info', 'La configuration initiale est terminée. Connectez-vous avec le compte existant.');

            return $this->redirectToRoute('app_login');
        }

        $data = new RegistrationData();
        $form = $this->createForm(RegistrationType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $account = new Account($data->email);
            $account->setPassword($passwordHasher->hashPassword($account, $data->plainPassword));
            $entityManager->persist($account);
            $entityManager->flush();
            $security->login($account, 'form_login');

            return $this->redirectToRoute('app_candidate_profile');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
            'googleEnabled' => $google->isConfigured(),
        ]);
    }

    #[Route('/connexion/google', name: 'app_google_start', methods: ['GET'])]
    public function googleStart(Request $request, GoogleOAuthClient $google): RedirectResponse
    {
        if (!$google->isConfigured()) {
            $this->addFlash('error', 'La connexion Google n’est pas encore configurée.');

            return $this->redirectToRoute('app_login');
        }

        $state = bin2hex(random_bytes(32));
        $request->getSession()->set('google_oauth_state', $state);

        return new RedirectResponse($google->authorizationUrl($this->googleCallbackUrl(), $state));
    }

    #[Route('/connexion/google/retour', name: 'app_google_callback', methods: ['GET'])]
    public function googleCallback(
        Request $request,
        GoogleOAuthClient $google,
        AccountRepository $accounts,
        EntityManagerInterface $entityManager,
        Security $security,
    ): RedirectResponse {
        $expectedState = $request->getSession()->remove('google_oauth_state');
        $state = $request->query->getString('state');
        $code = $request->query->getString('code');
        if (!is_string($expectedState) || $expectedState === '' || $state === '' || !hash_equals($expectedState, $state) || $code === '') {
            $this->addFlash('error', 'La connexion Google a été annulée ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $identity = $google->fetchIdentity($code, $this->googleCallbackUrl());
            $account = $accounts->findOneBy(['googleSubject' => $identity['subject']]);
            $account ??= $accounts->findOneBy(['email' => $identity['email']]);
            if (!$account instanceof Account) {
                if ($accounts->count([]) > 0) {
                    throw new \DomainException('Ce Job Matcher personnel est déjà associé à un autre compte.');
                }
                $account = new Account($identity['email']);
                $entityManager->persist($account);
            }
            $account->connectGoogle($identity['subject']);
            $entityManager->flush();
            $security->login($account, 'form_login');

            return $this->redirectToRoute('app_candidate_profile');
        } catch (\Throwable) {
            $this->addFlash('error', 'Impossible de vous connecter avec Google. Réessayez ou utilisez votre mot de passe.');

            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Cette route est interceptée par le pare-feu Symfony.');
    }

    private function googleCallbackUrl(): string
    {
        return $this->generateUrl('app_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
