<?php

declare(strict_types=1);

namespace App\Dto;

use App\Time\DayTimes;

final readonly class DaySummary
{
    public function __construct(
        public int $index,
        public \DateTimeImmutable $date,
        public bool $dayOff,
        public DayTimes $times,
        /** Daily balance in minutes, null while incomplete or off. */
        public ?int $balance,
        /** Cumulated balance in minutes up to this day, null when no balance yet. */
        public ?int $cumulated,
        public bool $isToday,
    ) {
    }

    public function name(): string
    {
        return $this->date->format('l');
    }
}
