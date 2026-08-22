<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\RegistrationType;
use App\Security\DTO\RegistrationData;
use App\Security\EmailVerificationService;
use App\Security\Entity\Account;
use App\Security\GoogleOAuthClient;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    private const GOOGLE_INTENT_LINK = 'link';
    private const GOOGLE_INTENT_LOGIN = 'login';

    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils, GoogleOAuthClient $google): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_post_login');
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
        EmailVerificationService $emailVerification,
        #[Autowire(service: 'limiter.email_verification')] RateLimiterFactory $emailVerificationLimiter,
        GoogleOAuthClient $google,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_post_login');
        }
        $data = new RegistrationData();
        $form = $this->createForm(RegistrationType::class, $data);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($accounts->findOneBy(['email' => mb_strtolower(trim($data->email))]) !== null) {
                $form->get('email')->addError(new FormError('Un compte utilise déjà cette adresse email.'));
            } else {
                $account = new Account($data->email);
                $account->setPassword($passwordHasher->hashPassword($account, $data->plainPassword));
                $entityManager->persist($account);
                $entityManager->flush();

                $limit = $emailVerificationLimiter->create(hash('sha256', $account->getEmail()))->consume();
                if (!$limit->isAccepted()) {
                    $this->addFlash('error', 'Compte créé, mais trop de demandes ont été effectuées. Demandez un nouveau lien dans quelques minutes.');
                } else {
                    try {
                        $emailVerification->send($account);
                        $this->addFlash('success', 'Compte créé. Consultez votre messagerie pour vérifier votre adresse avant de vous connecter.');
                    } catch (TransportExceptionInterface) {
                        $this->addFlash('error', 'Compte créé, mais l’email n’a pas pu être envoyé. Demandez un nouveau lien de vérification.');
                    }
                }

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
            'googleEnabled' => $google->isConfigured(),
        ]);
    }

    #[Route('/verification-email/{id<\d+>}', name: 'app_email_verify', methods: ['GET'])]
    public function verifyEmail(
        int $id,
        Request $request,
        AccountRepository $accounts,
        EmailVerificationService $emailVerification,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        $account = $accounts->find($id);
        if (!$account instanceof Account || !$emailVerification->isValid($request)) {
            $this->addFlash('error', 'Ce lien de vérification est invalide ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        $account->verifyEmail();
        $entityManager->flush();
        $this->addFlash('success', 'Votre adresse email est vérifiée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verification-email', name: 'app_email_resend', methods: ['GET', 'POST'])]
    public function resendVerificationEmail(
        Request $request,
        AccountRepository $accounts,
        EmailVerificationService $emailVerification,
        #[Autowire(service: 'limiter.email_verification')] RateLimiterFactory $emailVerificationLimiter,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('email_verification_resend', $request->request->getString('_csrf_token'))) {
                $this->addFlash('error', 'La demande a expiré. Veuillez recommencer.');

                return $this->redirectToRoute('app_email_resend');
            }

            $email = mb_strtolower(trim($request->request->getString('email')));
            $limit = $emailVerificationLimiter->create(hash('sha256', $email))->consume();
            if (!$limit->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes ont été effectuées. Réessayez dans quelques minutes.');

                return $this->redirectToRoute('app_email_resend');
            }

            $account = $accounts->findOneBy(['email' => $email]);
            if ($account instanceof Account && !$account->isEmailVerified()) {
                try {
                    $emailVerification->send($account);
                } catch (TransportExceptionInterface) {
                    // Keep the response generic so the endpoint cannot reveal whether an account exists.
                }
            }

            $this->addFlash('success', 'Si un compte non vérifié correspond à cette adresse, un nouveau lien vient d’être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/resend_verification.html.twig');
    }

    #[Route('/connexion/google', name: 'app_google_start', methods: ['GET'])]
    public function googleStart(Request $request, GoogleOAuthClient $google): RedirectResponse
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
        }
        if (!$google->isConfigured()) {
            $this->addFlash('error', 'La connexion Google n’est pas encore configurée.');

            return $this->redirectToRoute('app_login');
        }

        return $this->beginGoogleOAuth($request, $google, self::GOOGLE_INTENT_LOGIN);
    }

    #[Route('/profile/google', name: 'app_google_link', methods: ['GET'])]
    public function googleLink(
        Request $request,
        #[CurrentUser] Account $account,
        GoogleOAuthClient $google,
    ): RedirectResponse {
        if (!$google->isConfigured()) {
            $this->addFlash('error', 'La connexion Google n’est pas encore configurée.');

            return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
        }
        if ($account->isGoogleConnected()) {
            $this->addFlash('info', 'Votre compte Google est déjà associé.');

            return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
        }

        return $this->beginGoogleOAuth($request, $google, self::GOOGLE_INTENT_LINK);
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
        $intent = $request->getSession()->remove('google_oauth_intent');
        $state = $request->query->getString('state');
        $code = $request->query->getString('code');
        if (!is_string($expectedState) || $expectedState === '' || !in_array($intent, [self::GOOGLE_INTENT_LOGIN, self::GOOGLE_INTENT_LINK], true) || $state === '' || !hash_equals($expectedState, $state) || $code === '') {
            $this->addFlash('error', 'La connexion Google a été annulée ou a expiré.');

            return $this->redirectToRoute('app_login');
        }

        try {
            $identity = $google->fetchIdentity($code, $this->googleCallbackUrl());
        } catch (\Throwable) {
            $this->addFlash('error', 'Impossible de vous connecter avec Google. Réessayez ou utilisez votre mot de passe.');

            return $this->redirectToRoute('app_login');
        }

        if ($intent === self::GOOGLE_INTENT_LINK) {
            $authenticatedAccount = $security->getUser();
            if (!$authenticatedAccount instanceof Account || $authenticatedAccount->getId() === null) {
                $this->addFlash('error', 'Reconnectez-vous avec votre mot de passe avant d’associer Google.');

                return $this->redirectToRoute('app_login');
            }

            $account = $accounts->find($authenticatedAccount->getId());
            if (!$account instanceof Account) {
                $this->addFlash('error', 'Votre compte est introuvable. Reconnectez-vous avant d’associer Google.');

                return $this->redirectToRoute('app_login');
            }
            if ($identity['email'] !== $account->getEmail()) {
                $this->addFlash('error', 'L’adresse du compte Google doit correspondre à celle de votre profil.');

                return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
            }

            $subjectOwner = $accounts->findOneBy(['googleSubject' => $identity['subject']]);
            if ($subjectOwner instanceof Account && $subjectOwner->getId() !== $account->getId()) {
                $this->addFlash('error', 'Ce compte Google est déjà associé à un autre profil.');

                return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
            }

            $account->connectGoogle($identity['subject']);
            $entityManager->flush();
            $this->addFlash('success', 'Votre compte Google est maintenant associé.');

            return $this->redirectToRoute('app_candidate_profile', ['_fragment' => 'security']);
        }

        $account = $accounts->findOneBy(['googleSubject' => $identity['subject']]);
        if (!$account instanceof Account) {
            if ($accounts->findOneBy(['email' => $identity['email']]) instanceof Account) {
                $this->addFlash('error', 'Un compte local utilise déjà cette adresse. Connectez-vous avec votre mot de passe, puis associez Google depuis votre profil.');

                return $this->redirectToRoute('app_login');
            }

            $account = new Account($identity['email']);
            $account->connectGoogle($identity['subject']);
            $account->verifyEmail();
            $entityManager->persist($account);
            $entityManager->flush();
        }

        $security->login($account, 'form_login');

        return $this->redirectToRoute('app_post_login');
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Cette route est interceptée par le pare-feu Symfony.');
    }

    #[Route('/apres-connexion', name: 'app_post_login', methods: ['GET'])]
    public function postLogin(#[CurrentUser] Account $account): RedirectResponse
    {
        if ($account->getCandidateProfile()->getCvDocuments()->isEmpty()) {
            return $this->redirectToRoute('app_cv_upload');
        }

        return $this->redirectToRoute('app_candidate_profile');
    }

    private function googleCallbackUrl(): string
    {
        return $this->generateUrl('app_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function beginGoogleOAuth(Request $request, GoogleOAuthClient $google, string $intent): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set('google_oauth_state', $state);
        $request->getSession()->set('google_oauth_intent', $intent);

        return new RedirectResponse($google->authorizationUrl($this->googleCallbackUrl(), $state));
    }
}
