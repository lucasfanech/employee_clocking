<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\WorkDay;

final class AdminTest extends AppWebTestCase
{
    public function testAdminAreaIsForbiddenToRegularUsers(): void
    {
        $this->login($this->createUser());

        $this->client->request('GET', '/admin/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateAnAccount(): void
    {
        $this->login($this->createUser('admin@example.com', 'password123', true));

        $crawler = $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Create account')->form([
            'new_user[email]' => 'john@example.com',
            'new_user[plainPassword]' => 'password123',
            'new_user[admin]' => true,
        ]));
        self::assertResponseRedirects('/admin/users');

        $john = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'john@example.com']);
        self::assertNotNull($john);
        self::assertTrue($john->isAdmin());
        self::assertNotNull($john->getSchedule());

        $this->client->followRedirect();
        self::assertSelectorTextContains('table', 'john@example.com');
    }

    public function testAdminCanChangeRoleResetPasswordAndDeleteAnotherAccount(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', true);
        $john = $this->createUser('john@example.com', 'password123');
        $this->entityManager()->persist((new WorkDay($john, new \DateTimeImmutable('2024-03-04')))->setMorningIn(new \DateTimeImmutable('08:00')));
        $this->entityManager()->flush();
        $johnId = $john->getId();
        $this->login($admin);

        $crawler = $this->client->request('GET', '/admin/users');
        $row = $crawler->filter('tbody tr')->reduce(static fn ($tr) => str_contains($tr->text(), 'john@example.com'));

        // Promote
        $this->client->submit($row->filter('form[action$="/role"]')->form(['role' => 'admin']));
        self::assertResponseRedirects('/admin/users');
        $this->entityManager()->clear();
        self::assertTrue($this->entityManager()->find(User::class, $johnId)->isAdmin());

        // Reset password
        $oldHash = $this->entityManager()->find(User::class, $johnId)->getPassword();
        $this->client->submit($row->filter('form[action$="/password"]')->form(['password' => 'brandnewpass']));
        self::assertResponseRedirects('/admin/users');
        $this->entityManager()->clear();
        self::assertNotSame($oldHash, $this->entityManager()->find(User::class, $johnId)->getPassword());

        // Delete (cascades to schedule and work days)
        $this->client->submit($row->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects('/admin/users');
        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->find(User::class, $johnId));
        self::assertCount(0, $this->entityManager()->getRepository(WorkDay::class)->findAll());
    }

    public function testAdminCannotDeleteOrDemoteThemselves(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', true);
        $this->login($admin);

        $crawler = $this->client->request('GET', '/admin/users');
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');

        $this->client->request('POST', \sprintf('/admin/users/%d/delete', $admin->getId()), ['_token' => $token]);
        self::assertResponseRedirects('/admin/users');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'cannot delete your own account');

        $this->client->request('POST', \sprintf('/admin/users/%d/role', $admin->getId()), ['_token' => $token, 'role' => 'user']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'cannot remove your own administrator role');
        $this->entityManager()->clear();
        self::assertTrue($this->entityManager()->find(User::class, $admin->getId())->isAdmin());
    }

    public function testCsrfTokenIsRequired(): void
    {
        $admin = $this->createUser('admin@example.com', 'password123', true);
        $john = $this->createUser('john@example.com', 'password123');
        $this->login($admin);

        $this->client->request('POST', \sprintf('/admin/users/%d/delete', $john->getId()), ['_token' => 'forged']);

        // An invalid token is an authentication failure: the firewall bounces to the login page.
        self::assertResponseRedirects('http://localhost/login');
        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->find(User::class, $john->getId()), 'Nothing was deleted');
    }
}
