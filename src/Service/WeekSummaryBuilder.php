<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DaySummary;
use App\Dto\TodaySummary;
use App\Dto\WeekSummary;
use App\Entity\WorkSchedule;
use App\Time\DayTimes;
use App\Time\WeekReference;

/**
 * Turns the raw clocking times of a week into everything the week page displays.
 */
final class WeekSummaryBuilder
{
    public function __construct(
        private readonly WorkTimeCalculator $calculator,
    ) {
    }

    /**
     * @param list<DayTimes> $times   five entries, Monday to Friday
     * @param list<bool>     $daysOff five entries, Monday to Friday
     */
    public function build(
        WeekReference $week,
        WorkSchedule $schedule,
        array $times,
        array $daysOff,
        ?\DateTimeImmutable $today = null,
    ): WeekSummary {
        $today ??= new \DateTimeImmutable('today');
        $dates = $week->workingDays();
        $todayIndex = $week->indexOf($today);

        $balances = [];
        foreach ($dates as $i => $date) {
            $balances[] = $daysOff[$i]
                ? null
                : $this->calculator->dailyBalance($times[$i], $schedule, (int) $date->format('N'));
        }
        $cumulated = $this->calculator->cumulatedBalances($balances);

        $days = [];
        foreach ($dates as $i => $date) {
            $days[] = new DaySummary(
                index: $i,
                date: $date,
                dayOff: $daysOff[$i],
                times: $times[$i],
                balance: $balances[$i],
                cumulated: $cumulated[$i],
                isToday: $i === $todayIndex,
            );
        }

        $todaySummary = null;
        if (null !== $todayIndex && !$daysOff[$todayIndex]) {
            $todayTimes = $times[$todayIndex];
            $leaving = $this->calculator->estimateLeavingTime(
                $todayTimes,
                $schedule,
                (int) $dates[$todayIndex]->format('N'),
            );

            $cumulatedBefore = 0;
            for ($i = 0; $i < $todayIndex; ++$i) {
                $cumulatedBefore += $balances[$i] ?? 0;
            }

            $todaySummary = new TodaySummary(
                leavingTime: $leaving,
                adjustedLeavingTime: null === $leaving ? null : $this->calculator->adjustLeavingTime($leaving, $cumulatedBefore),
                cumulatedBefore: $cumulatedBefore,
                clockedOut: null !== $todayTimes->eveningOut,
            );
        }

        return new WeekSummary($week, $days, $todaySummary);
    }
}
