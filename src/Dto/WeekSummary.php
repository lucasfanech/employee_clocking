<?php

declare(strict_types=1);

namespace App\Dto;

use App\Time\Duration;
use App\Time\WeekReference;

final readonly class WeekSummary
{
    public function __construct(
        public WeekReference $week,
        /** @var list<DaySummary> Monday … Friday */
        public array $days,
        /** Null when today is not a working day of this week. */
        public ?TodaySummary $today,
    ) {
    }

    /** Plain array used by the JSON preview endpoint. */
    public function toArray(): array
    {
        return [
            'days' => array_map(static fn (DaySummary $d) => [
                'index' => $d->index,
                'date' => $d->date->format('Y-m-d'),
                'dayOff' => $d->dayOff,
                'balance' => null === $d->balance ? null : Duration::formatSigned($d->balance),
                'cumulated' => null === $d->cumulated ? null : Duration::formatSigned($d->cumulated),
            ], $this->days),
            'today' => null === $this->today ? null : [
                'leavingTime' => null === $this->today->leavingTime ? null : Duration::formatClock($this->today->leavingTime),
                'adjustedLeavingTime' => null === $this->today->adjustedLeavingTime ? null : Duration::formatClock($this->today->adjustedLeavingTime),
                'cumulatedBefore' => Duration::formatSigned($this->today->cumulatedBefore),
                'clockedOut' => $this->today->clockedOut,
            ],
        ];
    }
}
