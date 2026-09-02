<?php

declare(strict_types=1);

namespace App\Dto;

final readonly class TodaySummary
{
    public function __construct(
        /** Estimated clock-out time in minutes of the day, null before clocking in. */
        public ?int $leavingTime,
        /** Same, once the balance of the previous days is absorbed. */
        public ?int $adjustedLeavingTime,
        /** Balance cumulated on the previous days of the week, in minutes. */
        public int $cumulatedBefore,
        public bool $clockedOut,
    ) {
    }
}
