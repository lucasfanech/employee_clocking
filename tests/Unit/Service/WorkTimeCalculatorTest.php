<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Entity\WorkSchedule;
use App\Service\WorkTimeCalculator;
use App\Time\DayTimes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkTimeCalculatorTest extends TestCase
{
    private WorkTimeCalculator $calculator;
    private WorkSchedule $schedule;

    protected function setUp(): void
    {
        $this->calculator = new WorkTimeCalculator();
        // 38h30 a week (7h42 a day), 1h lunch break, 45 min allowed on Fridays.
        $this->schedule = (new WorkSchedule(new User()))
            ->setWeeklyMinutes(38 * 60 + 30)
            ->setLunchBreakMinutes(60)
            ->setShortLunchBreakMinutes(45)
            ->setShortLunchBreakDays([5]);
    }

    public function testIncompleteDayHasNoBalance(): void
    {
        $times = DayTimes::fromStrings('08:00', '12:00', '13:00', null);

        self::assertNull($this->calculator->dailyBalance($times, $this->schedule, 1));
    }

    #[DataProvider('balanceProvider')]
    public function testDailyBalance(string $in, string $lunchOut, string $lunchIn, string $out, int $isoDay, int $expected): void
    {
        $times = DayTimes::fromStrings($in, $lunchOut, $lunchIn, $out);

        self::assertSame($expected, $this->calculator->dailyBalance($times, $this->schedule, $isoDay));
    }

    /** @return iterable<string, array{string, string, string, string, int, int}> */
    public static function balanceProvider(): iterable
    {
        // 4h + 3h42 = 7h42 → exactly the daily target
        yield 'exact day' => ['08:00', '12:00', '13:00', '16:42', 1, 0];
        // 4h + 4h12 = 8h12 → +30
        yield 'overtime' => ['08:00', '12:00', '13:00', '17:12', 1, 30];
        // 4h + 3h12 = 7h12 → -30
        yield 'undertime' => ['08:00', '12:00', '13:00', '16:12', 1, -30];
        // 30 min lunch instead of 1h: worked 4h + 4h12 = 8h12 (+30) but 30 missing minutes of break are withdrawn → 0
        yield 'short break is not credited' => ['08:00', '12:00', '12:30', '16:42', 1, 0];
        // Same on a Friday: required break is 45 min, only 15 minutes are withdrawn → +15
        yield 'short break on exception day' => ['08:00', '12:00', '12:30', '16:42', 5, 15];
        // Break longer than required is simply lost working time
        yield 'long break' => ['08:00', '12:00', '14:00', '17:42', 1, 0];
    }

    public function testCumulatedBalancesSkipDaysWithoutBalance(): void
    {
        self::assertSame(
            [30, null, 0, -45, null],
            $this->calculator->cumulatedBalances([30, null, -30, -45, null]),
        );
    }

    public function testLeavingTimeNeedsMorningClockIn(): void
    {
        self::assertNull($this->calculator->estimateLeavingTime(new DayTimes(), $this->schedule, 1));
    }

    public function testLeavingTimeUsesRequiredBreakUntilLunchIsClocked(): void
    {
        $times = DayTimes::fromStrings('08:00', null, null, null);

        // 08:00 + 7h42 + 1h00 = 16:42
        self::assertSame(16 * 60 + 42, $this->calculator->estimateLeavingTime($times, $this->schedule, 1));
        // Friday: 08:00 + 7h42 + 0h45 = 16:27
        self::assertSame(16 * 60 + 27, $this->calculator->estimateLeavingTime($times, $this->schedule, 5));
    }

    public function testLeavingTimeUsesActualBreakWhenLonger(): void
    {
        $times = DayTimes::fromStrings('08:00', '12:00', '13:30', null);

        // 08:00 + 7h42 + 1h30 = 17:12
        self::assertSame(17 * 60 + 12, $this->calculator->estimateLeavingTime($times, $this->schedule, 1));
    }

    public function testLeavingTimeNeverUsesBreakShorterThanRequired(): void
    {
        $times = DayTimes::fromStrings('08:00', '12:00', '12:15', null);

        // 08:00 + 7h42 + 1h00 (not 0h15) = 16:42
        self::assertSame(16 * 60 + 42, $this->calculator->estimateLeavingTime($times, $this->schedule, 1));
    }

    public function testAdjustedLeavingTimeAbsorbsWeekBalance(): void
    {
        $leaving = 16 * 60 + 42;

        self::assertSame(16 * 60 + 12, $this->calculator->adjustLeavingTime($leaving, 30));
        self::assertSame(17 * 60 + 12, $this->calculator->adjustLeavingTime($leaving, -30));
        self::assertSame($leaving, $this->calculator->adjustLeavingTime($leaving, 0));
    }
}
