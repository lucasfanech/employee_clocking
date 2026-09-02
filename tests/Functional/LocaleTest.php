<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class LocaleTest extends AppWebTestCase
{
    public function testDefaultLocaleIsEnglish(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Know when you can leave.');
        self::assertSelectorExists('html[lang="en"]');
    }

    public function testBrowserLanguageIsUsedOnFirstVisit(): void
    {
        $this->client->request('GET', '/', [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9,en;q=0.5']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sachez quand vous pouvez partir.');
        self::assertSelectorExists('html[lang="fr"]');
    }

    public function testSwitchIsRememberedAcrossRequests(): void
    {
        $this->client->request('GET', '/locale/fr', [], [], ['HTTP_REFERER' => 'http://localhost/login']);
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bon retour');

        // Sticky: the browser now prefers English but the choice wins.
        $this->client->request('GET', '/', [], [], ['HTTP_ACCEPT_LANGUAGE' => 'en']);
        self::assertSelectorTextContains('h1', 'Sachez quand vous pouvez partir.');
        self::assertSelectorExists('.lang-switch a[aria-current][hreflang="fr"]');

        $this->client->request('GET', '/locale/en');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Know when you can leave.');
    }

    public function testSwitchIgnoresForeignReferers(): void
    {
        $this->client->request('GET', '/locale/fr', [], [], ['HTTP_REFERER' => 'https://evil.example/phishing']);

        self::assertResponseRedirects('/');
    }

    public function testUnknownLocaleIsNotFound(): void
    {
        $this->client->request('GET', '/locale/de');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAuthenticatedPagesAreTranslated(): void
    {
        $this->login($this->createUser());
        $this->client->request('GET', '/locale/fr');

        $crawler = $this->client->request('GET', '/week/2024/10');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Semaine 10 de 2024');
        self::assertSelectorTextContains('tbody tr:first-child th', 'Lundi');
        self::assertSelectorTextContains('button.btn-primary', 'Enregistrer les heures');

        $this->client->submit($crawler->selectButton('Enregistrer les heures')->form([
            'week[days][0][morningIn]' => '08:00',
        ]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Heures enregistrées.');
    }
}
