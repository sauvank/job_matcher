<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Candidate\Entity\CvDocument;
use App\Security\Entity\Account;
use App\Security\GoogleOAuthClient;
use App\Security\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SecurityControllerTest extends AuthenticatedWebTestCase
{
    public function testPrivatePagesRedirectAnonymousVisitorsToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/profile');

        self::assertResponseRedirects('/connexion');
    }

    public function testLoginPageIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Heureux de vous revoir');
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
        self::assertSelectorExists('a[href="/connexion/google"]');
    }

    public function testAuthenticatedOwnerCanLogOut(): void
    {
        $client = self::createClient();
        $this->loginOwner($client);
        $crawler = $client->request('GET', '/profile');
        $form = $crawler->filter('form[action="/deconnexion"]')->form();
        $client->submit($form);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorExists('a[href="/connexion"]');
    }

    public function testOwnerCanLogInWithEmailAndPassword(): void
    {
        $client = self::createClient();
        $account = $this->account('login-'.bin2hex(random_bytes(6)).'@example.test');
        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => mb_strtoupper($account->getEmail()),
            'password' => 'correct horse battery staple',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/apres-connexion');
        $client->followRedirect();
        self::assertResponseRedirects('/cv');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Déposer un CV');
    }

    public function testAccountWithACvIsSentToItsProfileAfterLogin(): void
    {
        $client = self::createClient();
        $uniqueId = bin2hex(random_bytes(6));
        $account = $this->account('login-with-cv-'.$uniqueId.'@example.test');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $document = new CvDocument(
            $account->getCandidateProfile(),
            'cv-present.pdf',
            'functional-login-with-cv-'.$uniqueId.'.pdf',
            'application/pdf',
            1024,
            hash('sha256', 'functional-login-with-cv-'.$uniqueId),
        );
        $entityManager->persist($document);
        $entityManager->flush();
        $client->loginUser($account);

        $client->request('GET', '/apres-connexion');

        self::assertResponseRedirects('/profile');
    }

    public function testGoogleLoginUsesStateAndExpectedCallback(): void
    {
        $client = self::createClient();
        $client->request('GET', '/connexion/google');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('location');
        self::assertNotNull($location);
        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('test-google-client', $query['client_id'] ?? null);
        self::assertSame('http://localhost/connexion/google/retour', $query['redirect_uri'] ?? null);
        self::assertSame($client->getRequest()->getSession()->get('google_oauth_state'), $query['state'] ?? null);
    }

    public function testGoogleLoginDoesNotAttachToExistingPasswordAccount(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $uniqueId = bin2hex(random_bytes(6));
        $email = 'existing-'.$uniqueId.'@example.test';
        $this->account($email);
        self::getContainer()->set(GoogleOAuthClient::class, $this->googleClient($email, 'google-existing-'.$uniqueId));

        $client->request('GET', '/connexion/google');
        $state = $client->getRequest()->getSession()->get('google_oauth_state');
        self::assertIsString($state);
        $client->request('GET', '/connexion/google/retour?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        self::assertResponseRedirects('/connexion');
        self::assertFalse($this->reloadAccount($email)->isGoogleConnected());
        $client->request('GET', '/profile');
        self::assertResponseRedirects('/connexion');
    }

    public function testGoogleLoginCreatesANewAccountForANewVerifiedIdentity(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $uniqueId = bin2hex(random_bytes(6));
        $email = 'new-google-'.$uniqueId.'@example.test';
        self::getContainer()->set(GoogleOAuthClient::class, $this->googleClient($email, 'google-new-'.$uniqueId));

        $client->request('GET', '/connexion/google');
        $state = $client->getRequest()->getSession()->get('google_oauth_state');
        self::assertIsString($state);
        $client->request('GET', '/connexion/google/retour?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        self::assertResponseRedirects('/apres-connexion');
        self::assertTrue($this->reloadAccount($email)->isGoogleConnected());
    }

    public function testAuthenticatedOwnerCanExplicitlyConnectMatchingGoogleAccount(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $uniqueId = bin2hex(random_bytes(6));
        $email = 'link-'.$uniqueId.'@example.test';
        $account = $this->account($email);
        $client->loginUser($account);
        self::getContainer()->set(GoogleOAuthClient::class, $this->googleClient($email, 'google-link-'.$uniqueId));

        $client->request('GET', '/profile/google');
        $state = $client->getRequest()->getSession()->get('google_oauth_state');
        self::assertIsString($state);
        $client->request('GET', '/connexion/google/retour?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        self::assertResponseRedirects('/profile#security');
        self::assertTrue($this->reloadAccount($email)->isGoogleConnected());
    }

    public function testAuthenticatedOwnerCannotConnectGoogleAccountWithAnotherEmail(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $uniqueId = bin2hex(random_bytes(6));
        $email = 'link-mismatch-'.$uniqueId.'@example.test';
        $account = $this->account($email);
        $client->loginUser($account);
        self::getContainer()->set(GoogleOAuthClient::class, $this->googleClient('other-'.$uniqueId.'@example.test', 'google-other-'.$uniqueId));

        $client->request('GET', '/profile/google');
        $state = $client->getRequest()->getSession()->get('google_oauth_state');
        self::assertIsString($state);
        $client->request('GET', '/connexion/google/retour?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        self::assertResponseRedirects('/profile#security');
        self::assertFalse($this->reloadAccount($email)->isGoogleConnected());
    }

    public function testAuthenticatedOwnerCannotConnectGoogleIdentityOwnedByAnotherAccount(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $uniqueId = bin2hex(random_bytes(6));
        $subject = 'google-already-owned-'.$uniqueId;
        $otherAccount = $this->account('google-owner-'.$uniqueId.'@example.test');
        $otherAccount->connectGoogle($subject);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->flush();

        $email = 'link-collision-'.$uniqueId.'@example.test';
        $account = $this->account($email);
        $client->loginUser($account);
        self::getContainer()->set(GoogleOAuthClient::class, $this->googleClient($email, $subject));

        $client->request('GET', '/profile/google');
        $state = $client->getRequest()->getSession()->get('google_oauth_state');
        self::assertIsString($state);
        $client->request('GET', '/connexion/google/retour?'.http_build_query([
            'state' => $state,
            'code' => 'authorization-code',
        ]));

        self::assertResponseRedirects('/profile#security');
        self::assertFalse($this->reloadAccount($email)->isGoogleConnected());
    }

    private function googleClient(string $email, string $subject): GoogleOAuthClient
    {
        return new GoogleOAuthClient(new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'access-token'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'sub' => $subject,
                'email' => $email,
                'email_verified' => true,
            ], JSON_THROW_ON_ERROR)),
        ]), 'test-google-client', 'test-google-secret');
    }

    private function reloadAccount(string $email): Account
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(AccountRepository::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(AccountRepository::class, $repository);
        $entityManager->clear();
        $account = $repository->findOneBy(['email' => $email]);
        self::assertInstanceOf(Account::class, $account);

        return $account;
    }
}
