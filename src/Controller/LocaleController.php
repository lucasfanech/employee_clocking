<?php

declare(strict_types=1);

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    /**
     * Language switch (FR / EN): remembers the choice and goes back to the previous page.
     */
    #[Route('/locale/{locale}', name: 'app_locale', requirements: ['locale' => 'en|fr'], methods: ['GET'])]
    public function switch(string $locale, Request $request): Response
    {
        $request->getSession()->set(LocaleSubscriber::SESSION_KEY, $locale);

        $referer = (string) $request->headers->get('referer', '');
        $host = parse_url($referer, \PHP_URL_HOST);
        $path = parse_url($referer, \PHP_URL_PATH);
        if ('' !== $referer && $host === $request->getHost() && \is_string($path) && str_starts_with($path, '/')) {
            $query = parse_url($referer, \PHP_URL_QUERY);

            return $this->redirect($path.(\is_string($query) && '' !== $query ? '?'.$query : ''));
        }

        return $this->redirectToRoute('app_landing');
    }
}
