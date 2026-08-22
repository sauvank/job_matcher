<?php

declare(strict_types=1);

namespace App\Tests\Functional;

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
        $account = $this->owner();
        $crawler = $client->request('GET', '/connexion');
        $form = $crawler->selectButton('Se connecter')->form([
            'email' => mb_strtoupper($account->getEmail()),
            'password' => 'correct horse battery staple',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/profile');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mon profil');
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
}
