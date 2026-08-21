<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class JobPagesControllerTest extends WebTestCase
{
    public function testJobSourcePageIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sources');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sources d’offres');
        self::assertSelectorTextContains('.page-heading', 'générée automatiquement');
        self::assertSelectorTextContains('.form-card h2', 'Ajouter une recherche');
    }

    public function testJobOfferPageIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Offres analysées');
        self::assertSelectorTextContains('.page-heading', 'compatibilité calculée par l’IA');
    }
}
