<?php

declare(strict_types=1);

namespace App\Time;

/**
 * The four clocking times of a day expressed as minutes since midnight.
 * A null value means "not clocked yet".
 */
final readonly class DayTimes
{
    public function __construct(
        public ?int $morningIn = null,
        public ?int $lunchOut = null,
        public ?int $afternoonIn = null,
        public ?int $eveningOut = null,
    ) {
    }

    public static function fromDateTimes(
        ?\DateTimeInterface $morningIn,
        ?\DateTimeInterface $lunchOut,
        ?\DateTimeInterface $afternoonIn,
        ?\DateTimeInterface $eveningOut,
    ): self {
        return new self(
            Duration::minutesOfDay($morningIn),
            Duration::minutesOfDay($lunchOut),
            Duration::minutesOfDay($afternoonIn),
            Duration::minutesOfDay($eveningOut),
        );
    }

    /**
     * Build from "HH:MM" strings (empty string / null = not clocked).
     *
     * @throws \InvalidArgumentException on malformed input
     */
    public static function fromStrings(?string $morningIn, ?string $lunchOut, ?string $afternoonIn, ?string $eveningOut): self
    {
        return new self(
            Duration::parseClock($morningIn),
            Duration::parseClock($lunchOut),
            Duration::parseClock($afternoonIn),
            Duration::parseClock($eveningOut),
        );
    }

    /** All four times are filled in: the day can be balanced. */
    public function isComplete(): bool
    {
        return null !== $this->morningIn && null !== $this->lunchOut
            && null !== $this->afternoonIn && null !== $this->eveningOut;
    }

    public function isEmpty(): bool
    {
        return null === $this->morningIn && null === $this->lunchOut
            && null === $this->afternoonIn && null === $this->eveningOut;
    }

    /** Lunch break actually taken, or null when lunch is not fully clocked. */
    public function actualBreakMinutes(): ?int
    {
        if (null === $this->lunchOut || null === $this->afternoonIn) {
            return null;
        }

        return abs($this->afternoonIn - $this->lunchOut);
    }
}
