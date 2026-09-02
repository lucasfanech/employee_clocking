<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\WorkDay;
use App\Form\WeekType;
use App\Repository\WorkDayRepository;
use App\Service\WeekSummaryBuilder;
use App\Time\DayTimes;
use App\Time\WeekReference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The weekly clocking grid.
 */
final class WeekController extends AbstractController
{
    private const ROUTE_REQUIREMENTS = ['year' => '\d{4}', 'week' => '\d{1,2}'];

    public function __construct(
        private readonly WorkDayRepository $workDays,
        private readonly WeekSummaryBuilder $summaryBuilder,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/week', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        $current = WeekReference::current();

        return $this->redirectToRoute('app_week', ['year' => $current->year, 'week' => $current->week]);
    }

    #[Route('/week/{year}/{week}', name: 'app_week', requirements: self::ROUTE_REQUIREMENTS, methods: ['GET', 'POST'])]
    public function week(WeekReference $reference, Request $request, #[CurrentUser] User $user): Response
    {
        $schedule = $user->getSchedule();
        if (null === $schedule) {
            $this->addFlash('warning', $this->translator->trans('Please set your working hours first.'));

            return $this->redirectToRoute('app_schedule');
        }

        $days = $this->workDays->findWeek($user, $reference);
        $form = $this->createForm(WeekType::class, ['days' => $days]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($days as $day) {
                $this->storeDay($day);
            }
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('Times saved.'));

            return $this->redirectToRoute('app_week', ['year' => $reference->year, 'week' => $reference->week]);
        }

        $summary = $this->summaryBuilder->build(
            $reference,
            $schedule,
            array_map(static fn (WorkDay $d) => $d->toDayTimes(), $days),
            array_map(static fn (WorkDay $d) => $d->isDayOff(), $days),
        );

        return $this->render('week/index.html.twig', [
            'week' => $reference,
            'previous' => $reference->previous(),
            'next' => $reference->next(),
            'current' => WeekReference::current(),
            'summary' => $summary,
            'schedule' => $schedule,
            'form' => $form,
        ]);
    }

    #[Route('/week/{year}/{week}/day-off/{index}', name: 'app_week_day_off', requirements: self::ROUTE_REQUIREMENTS + ['index' => '[0-4]'], methods: ['POST'])]
    #[IsCsrfTokenValid('day-off', tokenKey: '_token')]
    public function toggleDayOff(WeekReference $reference, int $index, Request $request, #[CurrentUser] User $user): Response
    {
        $day = $this->workDays->findWeek($user, $reference)[$index];
        $day->setDayOff(!$day->isDayOff());
        $this->storeDay($day);
        $this->entityManager->flush();

        $dayName = ucfirst((string) \IntlDateFormatter::formatObject($day->getDate(), 'EEEE', $request->getLocale()));
        $this->addFlash('success', $this->translator->trans(
            $day->isDayOff() ? '%day% marked as a day off.' : '%day% is a working day again.',
            ['%day%' => $dayName],
        ));

        return $this->redirectToRoute('app_week', ['year' => $reference->year, 'week' => $reference->week]);
    }

    /**
     * Live computation of the balances for times not saved yet (used by the week Stimulus controller).
     *
     * Payload: {"days": [{"morningIn": "08:00", "lunchOut": "", "afternoonIn": "", "eveningOut": ""}, … ×5]}
     */
    #[Route('/week/{year}/{week}/preview', name: 'app_week_preview', requirements: self::ROUTE_REQUIREMENTS, methods: ['POST'])]
    public function preview(WeekReference $reference, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $schedule = $user->getSchedule();
        if (null === $schedule) {
            return $this->json(['error' => 'Missing work schedule.'], Response::HTTP_CONFLICT);
        }

        $stored = $this->workDays->findWeek($user, $reference);
        $times = [];
        try {
            foreach ($this->readPreviewDays($request) as $i => $day) {
                $times[] = $stored[$i]->isDayOff()
                    ? new DayTimes()
                    : DayTimes::fromStrings(
                        $this->timeString($day, 'morningIn'),
                        $this->timeString($day, 'lunchOut'),
                        $this->timeString($day, 'afternoonIn'),
                        $this->timeString($day, 'eveningOut'),
                    );
            }
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $summary = $this->summaryBuilder->build(
            $reference,
            $schedule,
            $times,
            array_map(static fn (WorkDay $d) => $d->isDayOff(), $stored),
        );

        return $this->json($summary->toArray());
    }

    /**
     * @return list<array<string, mixed>> exactly five entries, Monday to Friday
     *
     * @throws BadRequestHttpException
     */
    private function readPreviewDays(Request $request): array
    {
        $days = $request->toArray()['days'] ?? null;
        if (!\is_array($days) || WeekReference::WORKING_DAYS !== \count($days) || !array_is_list($days)) {
            throw new BadRequestHttpException(\sprintf('"days" must be a list of %d entries.', WeekReference::WORKING_DAYS));
        }
        foreach ($days as $day) {
            if (!\is_array($day)) {
                throw new BadRequestHttpException('Each day must be an object of times.');
            }
        }

        return $days;
    }

    /** @param array<string, mixed> $day */
    private function timeString(array $day, string $field): ?string
    {
        $value = $day[$field] ?? null;

        return \is_scalar($value) ? (string) $value : null;
    }

    /** Persist a row that carries data, delete a row that became empty. */
    private function storeDay(WorkDay $day): void
    {
        if ($day->isDayOff()) {
            $day->clearTimes();
        }

        if ($day->isEmpty()) {
            if (null !== $day->getId()) {
                $this->entityManager->remove($day);
            }

            return;
        }

        $this->entityManager->persist($day);
    }
}

