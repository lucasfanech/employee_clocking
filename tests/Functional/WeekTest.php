<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\WorkDay;
use App\Time\WeekReference;

final class WeekTest extends AppWebTestCase
{
    public function testHomeRedirectsToTheCurrentWeek(): void
    {
        $this->login($this->createUser());
        $current = WeekReference::current();

        $this->client->request('GET', '/week');

        self::assertResponseRedirects(\sprintf('/week/%d/%d', $current->year, $current->week));
    }

    public function testWeekPageShowsTheFiveDays(): void
    {
        $this->login($this->createUser());

        $crawler = $this->client->request('GET', '/week/2024/10');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Week 10 of 2024');
        self::assertCount(5, $crawler->filter('tbody tr'));
        self::assertCount(20, $crawler->filter('input.clocking-time'));
        self::assertSelectorExists('a[href="/week/2024/9"]');
        self::assertSelectorExists('a[href="/week/2024/11"]');
    }

    public function testInvalidWeekIs404(): void
    {
        $this->login($this->createUser());

        $this->client->request('GET', '/week/2021/53');

        self::assertResponseStatusCodeSame(404);
    }

    public function testSavingTimesComputesBalancesAndCumulated(): void
    {
        $user = $this->createUser(); // defaults: 38h30 → 7h42 a day, 1h lunch, 45 min on Friday
        $this->login($user);

        $crawler = $this->client->request('GET', '/week/2024/10');
        $form = $crawler->selectButton('Save')->form([
            'week[days][0][morningIn]' => '08:00',
            'week[days][0][lunchOut]' => '12:00',
            'week[days][0][afternoonIn]' => '13:00',
            'week[days][0][eveningOut]' => '17:12',   // +0:30
            'week[days][1][morningIn]' => '08:00',
            'week[days][1][lunchOut]' => '12:00',
            'week[days][1][afternoonIn]' => '12:30',   // 30 min break instead of 1h
            'week[days][1][eveningOut]' => '16:42',    // 0:00 after the break penalty
            'week[days][2][morningIn]' => '09:00',     // incomplete day
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/week/2024/10');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'saved');

        $rows = $this->entityManager()->getRepository(WorkDay::class)->findBy(['user' => $user], ['date' => 'ASC']);
        self::assertCount(3, $rows, 'Only days with data are stored');
        self::assertSame('2024-03-04', $rows[0]->getDate()->format('Y-m-d'));
        self::assertSame('17:12', $rows[0]->getEveningOut()?->format('H:i'));

        $balances = $crawler->filter('[data-week-target="balance"]')->each(static fn ($node) => trim($node->text()));
        self::assertSame(['+0:30', '0:00', '—', '—', '—'], $balances);
        $cumulated = $crawler->filter('[data-week-target="cumulated"]')->each(static fn ($node) => trim($node->text()));
        self::assertSame(['+0:30', '+0:30', '—', '—', '—'], $cumulated);
    }

    public function testChronologyIsValidated(): void
    {
        $this->login($this->createUser());

        $crawler = $this->client->request('GET', '/week/2024/10');
        $this->client->submit($crawler->selectButton('Save')->form([
            'week[days][0][morningIn]' => '12:00',
            'week[days][0][lunchOut]' => '08:00',
        ]));

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('.error', 'chronological order');
    }

    public function testDayOffToggleClearsTimesAndDisablesInputs(): void
    {
        $user = $this->createUser();
        $this->login($user);

        $crawler = $this->client->request('GET', '/week/2024/10');
        $this->client->submit($crawler->selectButton('Save')->form([
            'week[days][3][morningIn]' => '08:00',
        ]));

        $crawler = $this->client->request('GET', '/week/2024/10');
        $this->client->submit($crawler->filter('form#day-off-3')->form());
        self::assertResponseRedirects('/week/2024/10');

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('tbody tr:nth-child(4)', 'day off');
        self::assertCount(4, $crawler->filter('tbody tr:nth-child(4) input.clocking-time[disabled]'));

        $day = $this->entityManager()->getRepository(WorkDay::class)->findOneBy(['user' => $user, 'date' => new \DateTimeImmutable('2024-03-07')]);
        self::assertNotNull($day);
        self::assertTrue($day->isDayOff());
        self::assertNull($day->getMorningIn());

        // Toggle back: the row is empty again and gets deleted.
        $crawler = $this->client->request('GET', '/week/2024/10');
        $this->client->submit($crawler->filter('form#day-off-3')->form());
        $this->entityManager()->clear();
        self::assertNull($this->entityManager()->getRepository(WorkDay::class)->findOneBy(['user' => $user, 'date' => new \DateTimeImmutable('2024-03-07')]));
    }

    public function testPreviewEndpointReturnsLiveBalances(): void
    {
        $this->login($this->createUser());

        $days = array_fill(0, 5, ['morningIn' => '', 'lunchOut' => '', 'afternoonIn' => '', 'eveningOut' => '']);
        $days[0] = ['morningIn' => '08:00', 'lunchOut' => '12:00', 'afternoonIn' => '13:00', 'eveningOut' => '16:12'];
        $days[4] = ['morningIn' => '08:00', 'lunchOut' => '12:00', 'afternoonIn' => '12:45', 'eveningOut' => '16:27'];

        $this->client->jsonRequest('POST', '/week/2024/10/preview', ['days' => $days]);

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('-0:30', $data['days'][0]['balance']);
        self::assertSame('0:00', $data['days'][4]['balance'], 'Friday allows the 45 min break');
        self::assertSame('-0:30', $data['days'][4]['cumulated']);
        self::assertNull($data['today'], '2024 week 10 is not the current week');
    }

    public function testPreviewEndpointRejectsMalformedTimes(): void
    {
        $this->login($this->createUser());

        $days = array_fill(0, 5, []);
        $days[0] = ['morningIn' => '25:99'];
        $this->client->jsonRequest('POST', '/week/2024/10/preview', ['days' => $days]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUserWithoutScheduleIsSentToTheConfiguration(): void
    {
        $user = $this->createUser();
        $this->entityManager()->remove($user->getSchedule());
        $this->entityManager()->flush();
        $this->entityManager()->clear();
        $this->login($this->entityManager()->find($user::class, $user->getId()));

        $this->client->request('GET', '/week/2024/10');

        self::assertResponseRedirects('/schedule');
    }
}
