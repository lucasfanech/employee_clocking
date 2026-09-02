<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\WorkDay;

final class ScheduleAndProfileTest extends AppWebTestCase
{
    public function testScheduleCanBeUpdated(): void
    {
        $user = $this->createUser();
        $this->login($user);

        $crawler = $this->client->request('GET', '/schedule');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save')->form([
            'work_schedule[weeklyMinutes][hours]' => '35',
            'work_schedule[weeklyMinutes][minutes]' => '0',
            'work_schedule[lunchBreakMinutes][hours]' => '0',
            'work_schedule[lunchBreakMinutes][minutes]' => '45',
            'work_schedule[shortLunchBreakMinutes][hours]' => '0',
            'work_schedule[shortLunchBreakMinutes][minutes]' => '30',
        ]);
        // Checkboxes: Monday … Friday (Friday is ticked by default)
        $form['work_schedule[shortLunchBreakDays]'][2]->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/week');

        $this->entityManager()->clear();
        $schedule = $this->entityManager()->find(User::class, $user->getId())->getSchedule();
        self::assertSame(35 * 60, $schedule->getWeeklyMinutes());
        self::assertSame(45, $schedule->getLunchBreakMinutes());
        self::assertSame(30, $schedule->getShortLunchBreakMinutes());
        self::assertSame([3, 5], $schedule->getShortLunchBreakDays());
    }

    public function testProfileEmailAndPasswordCanBeChanged(): void
    {
        $user = $this->createUser('jane@example.com', 'password123');
        $this->login($user);

        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $this->client->submit($crawler->selectButton('Update e-mail')->form([
            'profile[email]' => 'jane.doe@example.com',
        ]));
        self::assertResponseRedirects('/profile');

        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Change password')->form([
            'change_password[plainPassword][first]' => 'newpassword1',
            'change_password[plainPassword][second]' => 'newpassword1',
        ]));
        self::assertResponseRedirects('/profile');

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(User::class, $user->getId());
        self::assertSame('jane.doe@example.com', $reloaded->getEmail());
        self::assertNotSame($user->getPassword(), $reloaded->getPassword());
    }

    public function testResetDataDeletesTheWorkDaysOnly(): void
    {
        $user = $this->createUser();
        $this->login($user);

        $day = (new WorkDay($user, new \DateTimeImmutable('2024-03-04')))->setMorningIn(new \DateTimeImmutable('08:00'));
        $this->entityManager()->persist($day);
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Reset all my clocking data')->form());
        self::assertResponseRedirects('/profile');

        $this->entityManager()->clear();
        self::assertCount(0, $this->entityManager()->getRepository(WorkDay::class)->findAll());
        self::assertNotNull($this->entityManager()->find(User::class, $user->getId())->getSchedule());
    }
}
