<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sticky locale: the language chosen with the FR/EN switch is kept in the session
 * and applied to every following request (before Symfony's own LocaleListener).
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public const SESSION_KEY = '_locale';

    /** @param list<string> $enabledLocales */
    public function __construct(
        private readonly array $enabledLocales,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession() || $request->attributes->has('_locale')) {
            return;
        }

        $locale = $request->getSession()->get(self::SESSION_KEY);
        if (\is_string($locale) && \in_array($locale, $this->enabledLocales, true)) {
            // LocaleListener (priority 16) turns this attribute into the request locale.
            $request->attributes->set('_locale', $locale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}
