<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Repository\WorkDayRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] User $user,
        UserManager $userManager,
        EntityManagerInterface $entityManager,
    ): Response {
        $profileForm = $this->createForm(ProfileType::class, $user);
        $passwordForm = $this->createForm(ChangePasswordType::class);

        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('app_profile');
        }

        $passwordForm->handleRequest($request);
        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $userManager->setPassword($user, (string) $passwordForm->get('plainPassword')->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Password changed.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', [
            'profile_form' => $profileForm,
            'password_form' => $passwordForm,
        ]);
    }

    #[Route('/reset-data', name: 'app_profile_reset_data', methods: ['POST'])]
    #[IsCsrfTokenValid('reset-data', tokenKey: '_token')]
    public function resetData(#[CurrentUser] User $user, WorkDayRepository $workDays): Response
    {
        $deleted = $workDays->deleteAllForUser($user);
        $this->addFlash('success', \sprintf('All clocking data reset (%d day(s) removed).', $deleted));

        return $this->redirectToRoute('app_profile');
    }
}
