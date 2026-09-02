<?php

declare(strict_types=1);

namespace App\Time;

/**
 * Helpers to convert between minutes and "H:MM" / "HH:MM" strings.
 */
final class Duration
{
    private function __construct()
    {
    }

    /** Minutes elapsed since midnight for the time part of a date. */
    public static function minutesOfDay(?\DateTimeInterface $time): ?int
    {
        if (null === $time) {
            return null;
        }

        return (int) $time->format('G') * 60 + (int) $time->format('i');
    }

    /**
     * Parse "HH:MM" (or "HH:MM:SS") into minutes. Empty input yields null.
     *
     * @throws \InvalidArgumentException
     */
    public static function parseClock(?string $value): ?int
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        if (!preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $m)) {
            throw new \InvalidArgumentException(\sprintf('Invalid time "%s", expected HH:MM.', $value));
        }

        $hours = (int) $m[1];
        $minutes = (int) $m[2];
        if ($hours > 23 || $minutes > 59) {
            throw new \InvalidArgumentException(\sprintf('Invalid time "%s", expected HH:MM.', $value));
        }

        return $hours * 60 + $minutes;
    }

    /** Minutes of a day → "HH:MM" (wraps around midnight). */
    public static function formatClock(int $minutesOfDay): string
    {
        $minutesOfDay = ((int) round($minutesOfDay) % (24 * 60) + 24 * 60) % (24 * 60);

        return \sprintf('%02d:%02d', intdiv($minutesOfDay, 60), $minutesOfDay % 60);
    }

    /** Unsigned duration → "H:MM" (e.g. 2310 → "38:30"). */
    public static function formatDuration(int $minutes): string
    {
        $minutes = abs($minutes);

        return \sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** Signed balance → "+H:MM", "-H:MM" or "0:00". */
    public static function formatSigned(int $minutes): string
    {
        if (0 === $minutes) {
            return '0:00';
        }

        return ($minutes < 0 ? '-' : '+').self::formatDuration($minutes);
    }
}
