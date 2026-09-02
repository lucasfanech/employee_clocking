<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;

final class PublicPagesTest extends AppWebTestCase
{
    public function testLandingPageIsPublic(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Know when you can leave');
        self::assertSelectorExists('a[href="/register"]');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testLandingRedirectsAuthenticatedUsersToTheirWeek(): void
    {
        $this->login($this->createUser());

        $this->client->request('GET', '/');

        self::assertResponseRedirects('/week');
    }

    public function testProtectedPagesRequireAuthentication(): void
    {
        $this->client->request('GET', '/week');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testLoginWithValidCredentials(): void
    {
        $this->createUser('jane@example.com', 'password123');

        $crawler = $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Sign in')->form([
            '_username' => 'jane@example.com',
            '_password' => 'password123',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('http://localhost/week');
    }

    public function testLoginWithInvalidCredentialsShowsAnError(): void
    {
        $this->createUser('jane@example.com', 'password123');

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Sign in')->form([
            '_username' => 'jane@example.com',
            '_password' => 'wrong',
        ]));

        self::assertResponseRedirects('http://localhost/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid credentials');
    }

    public function testRegistrationCreatesTheAccountAndSignsIn(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'first@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('http://localhost/week');

        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'first@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isAdmin(), 'The first account administrates the instance');
        self::assertNotNull($user->getSchedule(), 'A default schedule is created');

        // The user is authenticated: the week page renders.
        $this->client->followRedirect();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Week');
    }

    public function testSecondRegisteredAccountIsNotAdmin(): void
    {
        $this->createUser('admin@example.com', 'password123', true);

        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'second@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]));

        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'second@example.com']);
        self::assertNotNull($user);
        self::assertFalse($user->isAdmin());
    }

    public function testRegistrationRejectsMismatchedPasswords(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->client->submit($crawler->selectButton('Create account')->form([
            'registration_form[email]' => 'first@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'different1',
        ]));

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('.error', 'must match');
    }
}
