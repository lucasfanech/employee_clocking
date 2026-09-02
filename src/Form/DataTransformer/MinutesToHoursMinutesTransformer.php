<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * int minutes  <->  ['hours' => int, 'minutes' => int]
 *
 * @implements DataTransformerInterface<int|null, array{hours: int|null, minutes: int|null}|null>
 */
final class MinutesToHoursMinutesTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (!\is_int($value) || $value < 0) {
            throw new TransformationFailedException('Expected a positive number of minutes.');
        }

        return ['hours' => intdiv($value, 60), 'minutes' => $value % 60];
    }

    public function reverseTransform(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $hours = $value['hours'] ?? null;
        $minutes = $value['minutes'] ?? null;
        if (null === $hours && null === $minutes) {
            return null;
        }

        return (int) ($hours ?? 0) * 60 + (int) ($minutes ?? 0);
    }
}
