<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserManager $userManager,
        UserRepository $users,
        Security $security,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // The very first account of the instance administrates it.
            $isFirstAccount = 0 === $users->countAll();

            $user = $userManager->create(
                $user->getEmail(),
                (string) $form->get('plainPassword')->getData(),
                $isFirstAccount,
            );

            $this->addFlash('success', $isFirstAccount
                ? 'Welcome! Your account is the first one, so it has been granted the administrator role.'
                : 'Welcome! Your account is ready, check your working hours below.');

            return $security->login($user, 'form_login', 'main')
                ?? $this->redirectToRoute('app_schedule');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form,
        ]);
    }
}
