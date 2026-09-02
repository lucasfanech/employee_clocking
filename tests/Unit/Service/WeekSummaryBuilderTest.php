<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\WorkSchedule;
use App\Service\WeekSummaryBuilder;
use App\Service\WorkTimeCalculator;
use App\Time\DayTimes;
use App\Time\WeekReference;
use PHPUnit\Framework\TestCase;

final class WeekSummaryBuilderTest extends TestCase
{
    private WeekSummaryBuilder $builder;
    private WorkSchedule $schedule;

    protected function setUp(): void
    {
        $this->builder = new WeekSummaryBuilder(new WorkTimeCalculator());
        $this->schedule = new WorkSchedule(new User()); // defaults: 38h30, 1h, 45 min on Friday
    }

    public function testBuildsBalancesCumulatedAndTodayEstimation(): void
    {
        $week = new WeekReference(2024, 10); // Monday 2024-03-04
        $times = [
            DayTimes::fromStrings('08:00', '12:00', '13:00', '17:12'), // +30
            DayTimes::fromStrings('08:00', '12:00', '13:00', '16:12'), // -30
            DayTimes::fromStrings('08:30', null, null, null),          // today, in progress
            new DayTimes(),
            new DayTimes(),
        ];
        $daysOff = [false, false, false, true, false];

        $summary = $this->builder->build($week, $this->schedule, $times, $daysOff, new \DateTimeImmutable('2024-03-06'));

        self::assertCount(5, $summary->days);
        self::assertSame([30, -30, null, null, null], array_map(static fn ($d) => $d->balance, $summary->days));
        self::assertSame([30, 0, null, null, null], array_map(static fn ($d) => $d->cumulated, $summary->days));
        self::assertTrue($summary->days[2]->isToday);
        self::assertTrue($summary->days[3]->dayOff);
        self::assertSame('Wednesday', $summary->days[2]->name());

        self::assertNotNull($summary->today);
        // 08:30 + 7h42 + 1h00 = 17:12, week balance so far is 0 → adjusted is identical
        self::assertSame(17 * 60 + 12, $summary->today->leavingTime);
        self::assertSame(17 * 60 + 12, $summary->today->adjustedLeavingTime);
        self::assertSame(0, $summary->today->cumulatedBefore);
        self::assertFalse($summary->today->clockedOut);
    }

    public function testAdjustedLeavingTimeUsesPreviousDaysOnly(): void
    {
        $week = new WeekReference(2024, 10);
        $times = [
            DayTimes::fromStrings('08:00', '12:00', '13:00', '17:42'), // +60
            DayTimes::fromStrings('08:00', null, null, null),          // today
            DayTimes::fromStrings('08:00', '12:00', '13:00', '17:42'), // future, ignored for the adjustment
            new DayTimes(),
            new DayTimes(),
        ];

        $summary = $this->builder->build($week, $this->schedule, $times, array_fill(0, 5, false), new \DateTimeImmutable('2024-03-05'));

        self::assertSame(16 * 60 + 42, $summary->today?->leavingTime);
        self::assertSame(15 * 60 + 42, $summary->today?->adjustedLeavingTime);
        self::assertSame(60, $summary->today?->cumulatedBefore);
    }

    public function testNoTodaySummaryOutsideOfTheWeekOrOnADayOff(): void
    {
        $week = new WeekReference(2024, 10);
        $empty = array_fill(0, 5, new DayTimes());

        $other = $this->builder->build($week, $this->schedule, $empty, array_fill(0, 5, false), new \DateTimeImmutable('2024-03-20'));
        self::assertNull($other->today);
        self::assertFalse($other->days[0]->isToday);

        $off = $this->builder->build($week, $this->schedule, $empty, [true, false, false, false, false], new \DateTimeImmutable('2024-03-04'));
        self::assertNull($off->today);
    }

    public function testClockedOutFlag(): void
    {
        $week = new WeekReference(2024, 10);
        $times = array_fill(0, 5, new DayTimes());
        $times[0] = DayTimes::fromStrings('08:00', '12:00', '13:00', '16:42');

        $summary = $this->builder->build($week, $this->schedule, $times, array_fill(0, 5, false), new \DateTimeImmutable('2024-03-04'));

        self::assertTrue($summary->today?->clockedOut);
        self::assertSame('0:00', $summary->toArray()['days'][0]['balance']);
        self::assertSame('16:42', $summary->toArray()['today']['leavingTime']);
    }
}
