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
        self::assertSelectorNotExists('form[name="job_source"]');
    }

    public function testJobOfferPageIsAvailable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/jobs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Offres importées');
        self::assertSelectorTextContains('.page-heading', 'compatibilité avec votre CV');
    }
}
