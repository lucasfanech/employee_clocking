<?php

declare(strict_types=1);

namespace App\Http;

use App\Time\WeekReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds a WeekReference controller argument from the {year} and {week} route parameters.
 */
final class WeekReferenceValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (WeekReference::class !== $argument->getType()) {
            return [];
        }

        $year = $request->attributes->get('year');
        $week = $request->attributes->get('week');
        if (!is_numeric($year) || !is_numeric($week)) {
            return [];
        }

        try {
            return [new WeekReference((int) $year, (int) $week)];
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }
}
