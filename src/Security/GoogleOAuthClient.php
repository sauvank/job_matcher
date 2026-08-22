<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GoogleOAuthClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $clientId,
        private string $clientSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{subject: string, email: string} */
    public function fetchIdentity(string $code, string $redirectUri): array
    {
        $tokenResponse = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ],
        ]);
        $token = $tokenResponse->toArray();
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Google did not return an access token.');
        }

        $profile = $this->httpClient->request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
            'auth_bearer' => $accessToken,
        ])->toArray();
        $subject = $profile['sub'] ?? null;
        $email = $profile['email'] ?? null;
        if (!is_string($subject) || $subject === '' || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || true !== ($profile['email_verified'] ?? false)) {
            throw new \RuntimeException('Google did not return a verified identity.');
        }

        return ['subject' => $subject, 'email' => mb_strtolower($email)];
    }
}
