<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\NewUserType;
use App\Repository\UserRepository;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(User::ROLE_ADMIN)]
final class UserController extends AbstractController
{
    /** Token id of the inline forms of the account list (see templates/admin/user/index.html.twig). */
    public const CSRF_TOKEN_ID = 'admin-user';

    public function __construct(
        private readonly UserManager $userManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findAllOrderedByEmail(),
        ]);
    }

    #[Route('/users/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(NewUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->create(
                $user->getEmail(),
                (string) $form->get('plainPassword')->getData(),
                (bool) $form->get('admin')->getData(),
            );
            $this->addFlash('success', \sprintf('Account "%s" created.', $user->getEmail()));

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/role', name: 'admin_user_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: '_token')]
    public function updateRole(#[MapEntity] User $user, Request $request, #[CurrentUser] User $current): Response
    {
        $admin = 'admin' === $request->request->get('role');

        if ($user === $current && !$admin) {
            $this->addFlash('danger', 'You cannot remove your own administrator role.');

            return $this->redirectToRoute('admin_users');
        }

        $this->userManager->setAdmin($user, $admin);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Role of "%s" updated.', $user->getEmail()));

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/password', name: 'admin_user_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: '_token')]
    public function updatePassword(#[MapEntity] User $user, Request $request): Response
    {
        $password = (string) $request->request->get('password', '');
        if (\strlen($password) < ChangePasswordType::MIN_LENGTH) {
            $this->addFlash('danger', \sprintf('The password should be at least %d characters.', ChangePasswordType::MIN_LENGTH));

            return $this->redirectToRoute('admin_users');
        }

        $this->userManager->setPassword($user, $password);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Password of "%s" updated.', $user->getEmail()));

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/users/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsCsrfTokenValid(self::CSRF_TOKEN_ID, tokenKey: '_token')]
    public function delete(#[MapEntity] User $user, #[CurrentUser] User $current): Response
    {
        if ($user === $current) {
            $this->addFlash('danger', 'You cannot delete your own account.');

            return $this->redirectToRoute('admin_users');
        }

        // Schedule and work days are removed by cascade.
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Account "%s" deleted.', $user->getEmail()));

        return $this->redirectToRoute('admin_users');
    }
}
