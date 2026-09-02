<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\WorkSchedule;
use App\Time\DayTimes;

/**
 * The clocking rules of the application. Everything is expressed in minutes.
 *
 *  - A day is balanced only once its four times are clocked:
 *      balance = worked − (weekly hours ÷ 5)
 *    where worked = (lunch out − morning in) + (evening out − afternoon in).
 *    A lunch break shorter than the required one is not credited: the missing
 *    minutes are removed from the balance.
 *  - The cumulated balance is the running sum of the daily balances of the week.
 *  - The estimated leaving time of a day is
 *      morning in + (weekly hours ÷ 5) + lunch break,
 *    the lunch break being the required one until lunch is clocked, then the
 *    break actually taken (never less than the required one).
 *  - The adjusted leaving time also absorbs the balance cumulated on the
 *    previous days of the week (ahead → leave earlier, behind → leave later).
 */
final class WorkTimeCalculator
{
    /**
     * Balance of a day in minutes (positive = overtime), or null while the day is incomplete.
     */
    public function dailyBalance(DayTimes $times, WorkSchedule $schedule, int $isoWeekDay): ?int
    {
        if (!$times->isComplete()) {
            return null;
        }

        $worked = ($times->lunchOut - $times->morningIn) + ($times->eveningOut - $times->afternoonIn);
        $balance = $worked - $schedule->getDailyMinutes();

        $required = $schedule->getRequiredBreakMinutes($isoWeekDay);
        $actual = $times->afternoonIn - $times->lunchOut;
        if ($actual < $required) {
            $balance -= $required - $actual;
        }

        return (int) round($balance);
    }

    /**
     * Running sum of the daily balances; null where the day has no balance yet.
     *
     * @param list<int|null> $balances
     *
     * @return list<int|null>
     */
    public function cumulatedBalances(array $balances): array
    {
        $total = 0;
        $cumulated = [];
        foreach ($balances as $balance) {
            if (null === $balance) {
                $cumulated[] = null;
                continue;
            }
            $total += $balance;
            $cumulated[] = $total;
        }

        return $cumulated;
    }

    /**
     * Estimated clock-out time (minutes of the day), or null before the morning clock-in.
     */
    public function estimateLeavingTime(DayTimes $times, WorkSchedule $schedule, int $isoWeekDay): ?int
    {
        if (null === $times->morningIn) {
            return null;
        }

        $break = $schedule->getRequiredBreakMinutes($isoWeekDay);
        $actual = $times->actualBreakMinutes();
        if (null !== $actual) {
            $break = max($actual, $break);
        }

        return (int) round($times->morningIn + $schedule->getDailyMinutes() + $break);
    }

    /**
     * Leaving time once the balance cumulated on the previous days is taken into account.
     */
    public function adjustLeavingTime(int $leavingTime, int $cumulatedBefore): int
    {
        return $leavingTime - $cumulatedBefore;
    }
}
