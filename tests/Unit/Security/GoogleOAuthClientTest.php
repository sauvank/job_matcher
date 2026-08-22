<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\GoogleOAuthClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleOAuthClientTest extends TestCase
{
    public function testItExchangesTheCodeForAVerifiedIdentity(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'access-token'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'sub' => 'google-subject',
                'email' => 'Owner@Example.test',
                'email_verified' => true,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $client = new GoogleOAuthClient($httpClient, 'client-id', 'client-secret');

        self::assertSame(
            ['subject' => 'google-subject', 'email' => 'owner@example.test'],
            $client->fetchIdentity('authorization-code', 'https://app.test/connexion/google/retour'),
        );
    }

    public function testItRejectsAnUnverifiedEmail(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'access-token'], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'sub' => 'google-subject',
                'email' => 'owner@example.test',
                'email_verified' => false,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $client = new GoogleOAuthClient($httpClient, 'client-id', 'client-secret');

        $this->expectException(\RuntimeException::class);
        $client->fetchIdentity('authorization-code', 'https://app.test/connexion/google/retour');
    }
}
