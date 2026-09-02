<?php

declare(strict_types=1);

namespace App\Tests\Unit\Time;

use App\Time\Duration;
use PHPUnit\Framework\TestCase;

final class DurationTest extends TestCase
{
    public function testParseClock(): void
    {
        self::assertNull(Duration::parseClock(null));
        self::assertNull(Duration::parseClock(''));
        self::assertSame(0, Duration::parseClock('00:00'));
        self::assertSame(8 * 60 + 5, Duration::parseClock('08:05'));
        self::assertSame(17 * 60 + 30, Duration::parseClock('17:30:00'));
    }

    public function testParseClockRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Duration::parseClock('25:00');
    }

    public function testFormatting(): void
    {
        self::assertSame('17:30', Duration::formatClock(17 * 60 + 30));
        self::assertSame('00:30', Duration::formatClock(24 * 60 + 30));
        self::assertSame('38:30', Duration::formatDuration(38 * 60 + 30));
        self::assertSame('+1:30', Duration::formatSigned(90));
        self::assertSame('-0:30', Duration::formatSigned(-30));
        self::assertSame('-1:00', Duration::formatSigned(-60));
        self::assertSame('0:00', Duration::formatSigned(0));
    }

    public function testMinutesOfDay(): void
    {
        self::assertNull(Duration::minutesOfDay(null));
        self::assertSame(9 * 60 + 15, Duration::minutesOfDay(new \DateTimeImmutable('2024-03-04 09:15:59')));
    }
}
