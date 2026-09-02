<?php

declare(strict_types=1);

namespace App\Twig;

use App\Time\Duration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class TimeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // 90 → "+1:30", -30 → "-0:30", 0 → "0:00"
            new TwigFilter('signed_minutes', static fn (int|float|null $minutes): ?string => null === $minutes ? null : Duration::formatSigned((int) round($minutes))),
            // 2310 → "38:30"
            new TwigFilter('duration_minutes', static fn (int|float|null $minutes): ?string => null === $minutes ? null : Duration::formatDuration((int) round($minutes))),
            // 1050 → "17:30"
            new TwigFilter('clock_minutes', static fn (int|float|null $minutes): ?string => null === $minutes ? null : Duration::formatClock((int) round($minutes))),
        ];
    }
}
