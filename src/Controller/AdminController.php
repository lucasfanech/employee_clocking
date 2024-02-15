<?php
// src/Controller/AdminController.php

namespace App\Controller;

use App\Form\UserType;
use App\Entity\User;
use App\Entity\Conf;
use App\Entity\Clocking;
use App\Entity\DayOff;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;



class AdminController extends AbstractController
{
    #[Route('/admin/create-user', name: 'admin_create_user')]
    public function createUser(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Encodez le mot de passe, attribuez un ID unique à l'utilisateur, etc.
            $user->setPassword(password_hash($form->get('password')->getData(), PASSWORD_DEFAULT));
            // multiple roles
            $user->setRoles($form->get('roles')->getData());


            // Enregistrez l'utilisateur dans la base de données
            $entityManager->persist($user);
            $entityManager->flush();

            // create conf for user created
            //DEFAULT: INSERT INTO `conf` (`id`, `time_lunch_break`, `time_exception`, `days_exception`, `time_hours_to_do_week`, `user_id`) VALUES (NULL, '01:00:00', '00:45:00', 'Fri;', '38:30:00', '2')
            $conf = new Conf();
            $conf->setUser($user);
            $conf->setTimeLunchBreak(new \DateTime('01:00:00'));
            $conf->setTimeException(new \DateTime('00:45:00'));
            $conf->setDaysException('Fri;');
            $conf->setTimeHoursToDoWeek('38:30:00');
            $entityManager->persist($conf);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/create_user.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Logique pour le tableau de bord de l'administrateur

        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function manageUsers(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/manage_users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/{id}/update-role', name: 'admin_update_user_role')]
    public function updateUserRole(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $newRole = $request->request->get('role');
        $user->setRoles([$newRole]);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/user/{id}/update-password', name: 'admin_update_user_password')]
    public function updateUserPassword(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $newPassword = $request->request->get('password');
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/user/delete/{id}', name: 'delete_user')]
    public function deleteUser(User $user, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Supprime les références dans les autres tables
        $this->deleteUserReferences($user, $entityManager);

        // Supprime l'utilisateur
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('admin_users');
    }

    private function deleteUserReferences(User $user, EntityManagerInterface $entityManager): void
    {
        // Supprimer les références dans les autres tables (clocking, conf, day_off, etc.)

        // get clocking for user
        $clockings = $entityManager->getRepository(Clocking::class)->findBy(['user' => $user]);
        foreach ($clockings as $clocking){
            $entityManager->remove($clocking);
            $entityManager->flush();
        }

        // get conf for user
        $conf = $entityManager->getRepository(Conf::class)->findOneBy(['user' => $user]);
        $entityManager->remove($conf);

        // get day off for user
        $dayOffs = $entityManager->getRepository(DayOff::class)->findBy(['user' => $user]);
        foreach ($dayOffs as $dayOff){
            $entityManager->remove($dayOff);
            $entityManager->flush();
        }



    }
}
