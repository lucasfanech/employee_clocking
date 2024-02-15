<?php
// src/Controller/ProfileController.php

namespace App\Controller;

use App\Form\ProfileType;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Entity\Clocking;
use App\Entity\DayOff;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'user_profile')]
    public function userProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ProfileType::class, $user);
        $passwordForm = $this->createForm(ChangePasswordType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Profile updated successfully.');
        }

        return $this->render('user/profile.html.twig', [
            'form' => $form->createView(),
            'passwordForm' => $passwordForm->createView(),
        ]);
    }

    #[Route('/profile/change-password', name: 'change_password')]
    public function changePassword(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $passwordForm = $this->createForm(ChangePasswordType::class);

        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            // Encodage du nouveau mot de passe et mise à jour en base de données
            $newPassword = $passwordForm->get('plainPassword')->getData();
            $user->setPassword(password_hash($newPassword, PASSWORD_DEFAULT));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Password changed successfully.');

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/change_password.html.twig', [
            'passwordForm' => $passwordForm->createView(),
        ]);
    }

    #[Route('/profile/reset-data', name: 'reset_data')]
    public function resetData(EntityManagerInterface $entityManager): Response
    {
        // Logique de réinitialisation des données (clocking, dayoffs, etc.)
        // Ajoutez votre logique de réinitialisation ici

        $user = $this->getUser();
        $clocking = $entityManager->getRepository(Clocking::class)->findBy(['user' => $user]);
        $dayOff = $entityManager->getRepository(DayOff::class)->findBy(['user' => $user]);

        foreach ($clocking as $clock) {
            $entityManager->remove($clock);
        }

        foreach ($dayOff as $day) {
            $entityManager->remove($day);
        }

        $entityManager->flush();


        $this->addFlash('success', 'All data reset successfully.');

        return $this->redirectToRoute('user_profile');
    }
}
