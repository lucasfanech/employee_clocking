<?php

declare(strict_types=1);

namespace App\Tests\Unit\Time;

use App\Time\WeekReference;
use PHPUnit\Framework\TestCase;

final class WeekReferenceTest extends TestCase
{
    public function testWorkingDaysRunFromMondayToFriday(): void
    {
        $week = new WeekReference(2024, 1);
        $days = array_map(static fn (\DateTimeImmutable $d) => $d->format('Y-m-d'), $week->workingDays());

        self::assertSame(['2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04', '2024-01-05'], $days);
        self::assertSame('2024-01-05', $week->friday()->format('Y-m-d'));
    }

    public function testNavigationAcrossYearBoundaries(): void
    {
        // 2020 has 53 ISO weeks
        $last = new WeekReference(2020, 53);
        self::assertTrue($last->next()->equals(new WeekReference(2021, 1)));
        self::assertTrue((new WeekReference(2021, 1))->previous()->equals($last));

        // 2021 has 52 weeks
        self::assertTrue((new WeekReference(2021, 52))->next()->equals(new WeekReference(2022, 1)));
    }

    public function testFromDateUsesIsoYear(): void
    {
        // 2024-12-30 belongs to ISO week 1 of 2025
        $week = WeekReference::fromDate(new \DateTimeImmutable('2024-12-30'));

        self::assertSame(2025, $week->year);
        self::assertSame(1, $week->week);
    }

    public function testRejectsInvalidWeeks(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WeekReference(2021, 53);
    }

    public function testIndexOf(): void
    {
        $week = new WeekReference(2024, 10);

        self::assertSame(0, $week->indexOf(new \DateTimeImmutable('2024-03-04')));
        self::assertSame(4, $week->indexOf(new \DateTimeImmutable('2024-03-08')));
        self::assertNull($week->indexOf(new \DateTimeImmutable('2024-03-09'))); // Saturday
    }
}
