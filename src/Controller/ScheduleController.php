<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\WorkScheduleType;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Working-hours configuration of the connected user.
 */
final class ScheduleController extends AbstractController
{
    #[Route('/schedule', name: 'app_schedule', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[CurrentUser] User $user,
        UserManager $userManager,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): Response {
        $isNew = null === $user->getSchedule();
        $schedule = $userManager->ensureSchedule($user);

        $form = $this->createForm(WorkScheduleType::class, $schedule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', $translator->trans('Working hours saved.'));

            return $this->redirectToRoute('app_home');
        }

        return $this->render('schedule/edit.html.twig', [
            'form' => $form,
            'schedule' => $schedule,
            'is_new' => $isNew,
        ]);
    }
}
