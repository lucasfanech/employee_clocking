<?php

declare(strict_types=1);

namespace App\Time;

/**
 * An ISO-8601 week (year + week number) and its Monday-to-Friday working days.
 */
final readonly class WeekReference
{
    public const WORKING_DAYS = 5;

    public function __construct(
        public int $year,
        public int $week,
    ) {
        if ($year < 1970 || $year > 9999) {
            throw new \InvalidArgumentException(\sprintf('Invalid year %d.', $year));
        }
        if ($week < 1 || $week > self::weeksInYear($year)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ISO week %d for year %d.', $week, $year));
        }
    }

    public static function fromDate(\DateTimeInterface $date): self
    {
        return new self((int) $date->format('o'), (int) $date->format('W'));
    }

    public static function current(?\DateTimeImmutable $now = null): self
    {
        return self::fromDate($now ?? new \DateTimeImmutable('today'));
    }

    public static function weeksInYear(int $year): int
    {
        // 28 December is always in the last ISO week of its year.
        return (int) (new \DateTimeImmutable(\sprintf('%04d-12-28', $year)))->format('W');
    }

    public function monday(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today'))->setISODate($this->year, $this->week, 1)->setTime(0, 0);
    }

    public function friday(): \DateTimeImmutable
    {
        return $this->monday()->modify('+4 days');
    }

    /** @return list<\DateTimeImmutable> Monday … Friday */
    public function workingDays(): array
    {
        $monday = $this->monday();
        $days = [];
        for ($i = 0; $i < self::WORKING_DAYS; ++$i) {
            $days[] = $monday->modify(\sprintf('+%d days', $i));
        }

        return $days;
    }

    public function previous(): self
    {
        return self::fromDate($this->monday()->modify('-7 days'));
    }

    public function next(): self
    {
        return self::fromDate($this->monday()->modify('+7 days'));
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year && $this->week === $other->week;
    }

    /** Index (0 = Monday … 4 = Friday) of a date within this week, or null. */
    public function indexOf(\DateTimeInterface $date): ?int
    {
        foreach ($this->workingDays() as $index => $day) {
            if ($day->format('Y-m-d') === $date->format('Y-m-d')) {
                return $index;
            }
        }

        return null;
    }

    public function label(): string
    {
        return \sprintf('Week %d of %d', $this->week, $this->year);
    }
}
