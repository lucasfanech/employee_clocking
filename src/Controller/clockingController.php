<?php

namespace App\Controller;

use App\Entity\Clocking;
use App\Entity\Conf;
use App\Entity\DayOff;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Security;

class clockingController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function home(): Response
    {
        return $this->redirectToRoute('login');
    }

    #[Route('/login', name: 'login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, redirige vers la homepage
        if ($this->getUser()) {
            $year = date('Y');
            $week = date('W');
            return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
        }

        // Récupère les erreurs de la dernière tentative de connexion (le cas échéant)
        $error = $authenticationUtils->getLastAuthenticationError();

        // Récupère le dernier nom d'utilisateur saisi (si saisi)
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        // Le routeur interceptera cette demande et la traitera en conséquence
    }

    #[Route('/home/{year}/{week}', name: 'home')]
    public function homepage(string $year = null, string $week = null, EntityManagerInterface $entityManager, Security $security): Response{
        if ($week == 0){
            $week = 52;
            $year = $year - 1;
            return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
        }
        // if week contains 0 before, remove it
        if (substr($week, 0, 1) == "0"){
            $week = substr($week, 1);
        }
        if ($week > 52){
            $week = $week - 52;
            $year = $year + 1;
            return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
        }

        // get the connected user and get his id
        $user = $security->getUser();
        $userId = $user->getId();

        $message = "";
        $message2 = "";
        $days = array();
        if ($year){
            if ($week){
                $message = "Week n° ".$week." of ".$year;

                // Détermination des jours Lundi à vendredi
                // get the date of the first day of the week $week of the year $year
                $date = new \DateTime();
                $date->setISODate($year, $week);
                $date->setTime(0,0,0);
                $date->modify('monday this week');
                $monday = $date->format('Y-m-d');
                $date->modify('friday this week');
                $friday = $date->format('Y-m-d');
                $message2 = " From ".$monday." to ".$friday;

                //loop of 5 days and put in array
                $days = array();
                $daysName = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday");
                $partOfDay = array("Morning", "Lunch", "Afternoon", "Evening");
                $date->modify('monday this week');
                for ($i = 0; $i < 5; $i++){
                    $days[$i]['date'] = $date->format('Y-m-d');
                    $days[$i]['name'] = $daysName[$i];

                    // loop of 4 parts of day
                    for ($j = 0; $j < 4; $j++){
                        // get from DB table clocking where week_ref == $year.$week and day == $daysName[$i] and partOfDay == $partOfDay[$j]
                        $clocking = $entityManager->getRepository(Clocking::class)->findBy(array('week_ref' => $year.$week, 'day' => $daysName[$i], 'partOfDay' => $partOfDay[$j], 'user' => $userId));
                        if ($clocking){
                            $days[$i][$partOfDay[$j]] = $clocking[0]->getClockingHour()->format('H:i');
                        }
                        else{
                            $days[$i][$partOfDay[$j]] = "";
                        }
                    }
                    $date->modify('+1 day');
                }

                // get configuration
                $conf = $entityManager->getRepository(Conf::class)->findBy(array('user' => $userId));
                if (!$conf){
                    $config = 0;
                    $this->addFlash('error', 'Missing configuration!');
                    $entityManager->flush();
                    return $this->render('config/config.html.twig', [
                        'config' => $config,
                    ]);
                }
                else{
                    $config = 1;
                    $lunchBreakTime = $conf[0]->getTimeLunchBreak();
                    $hoursToDoInWeek = $conf[0]->getTimeHoursToDoWeek();
                    $exceptionTime = $conf[0]->getTimeException();
                    $exceptionDays = $conf[0]->getDaysException();
                    // convert to array by separating by ";" and remove empty values
                    $exceptionDays = array_filter(explode(";", $exceptionDays));
                    $weekDays = array("Mon", "Tue", "Wed", "Thu", "Fri");
                    $days_array = array();
                    for ($i = 0; $i < 5; $i++){
                        foreach ($exceptionDays as $exceptionDay){
                            if ($exceptionDay == $weekDays[$i]){
                                $days_array[$i] = 1;
                            }else{
                                $days_array[$i] = 0;
                            }
                        }
                    }
                }

                // Days off
                $daysOff = $entityManager->getRepository(DayOff::class)->findBy(array('week_ref' => $year.$week, 'user' => $userId));
                $daysOff_array = array();
                for ($i = 0; $i < 5; $i++){
                    $daysOff_array[$i] = 0;
                }
                foreach ($daysOff as $dayOff){
                    for ($i = 0; $i < 5; $i++){
                        if ($dayOff->getDay() == $i){
                            $daysOff_array[$i] = 1;
                        }
                    }
                }
            }else{
                $year = date('Y');
                $week = date('W');
                return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
            }
        }else{
            $year = date('Y');
            $week = date('W');
            return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
        }
        return $this->render('clocking/homepage.html.twig', [
            'title' => 'Clocking',
            'message' => $message,
            'message2' => $message2,
            'week' => $week,
            'days' => $days,
            'year' => $year,
            'config' => $config,
            'lunchBreakTime' => $lunchBreakTime,
            'hoursToDoInWeek' => $hoursToDoInWeek,
            'exceptionTime' => $exceptionTime,
            'exceptionDays' => $days_array,
            'daysOff' => $daysOff_array,
            'user' => $user,
        ]);

    }

    #[Route('/home/save/{year}/{week}', name: 'clocking_save')]
    public function save(EntityManagerInterface $entityManager, Request $request, string $year = null, string $week = null, Security $security): Response{
        // get the connected user and get his id
        $user = $security->getUser();
        $userId = $user->getId();

        $message = "All clocking data saved";
        // POST data
        $data = $request->request->all();

        //put in array days : 'Morning': $data['c1_0'], 'Lunch': $data['c2_0'], 'Afternoon': $data['c3_0'], 'Evening': $data['c4_0']
        $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');
        $daysData = array();
        for ($i = 0; $i < 5; $i++){
            if (isset($data['c1_'.$i])){
                if ($data['c1_'.$i] != ""){
                    $daysData[$i]['Morning'] = new DateTime($data['c1_'.$i]);
                }else{
                    $daysData[$i]['Morning'] = null;
                }
                if ($data['c2_'.$i] != ""){
                    $daysData[$i]['Lunch'] = new DateTime($data['c2_'.$i]);
                }else{
                    $daysData[$i]['Lunch'] = null;
                }
                if ($data['c3_'.$i] != ""){
                    $daysData[$i]['Afternoon'] = new DateTime($data['c3_'.$i]);
                }else{
                    $daysData[$i]['Afternoon'] = null;
                }
                if ($data['c4_'.$i] != ""){
                    $daysData[$i]['Evening'] = new DateTime($data['c4_'.$i]);
                }else{
                    $daysData[$i]['Evening'] = null;
                }
            }

        }

        // SAVE DATA
        // check every entries in clocking table with week_ref = $year.$week
        $clocking = $entityManager->getRepository(Clocking::class)->findBy(['week_ref' => $year.$week, 'user' => $userId]);
        if ($clocking){
            // drop all data with week_ref = $year.$week
            foreach ($clocking as $clock){
                $entityManager->remove($clock);
            }
        }
        // insert new data with week_ref = $year.$week and days data FOR EACH DAY OF THE WEEK
        for ($i = 0; $i < 5; $i++){
            if (isset($daysData[$i])){
                // insert new data Monday
                if ($daysData[$i]['Morning'] != null){
                    $clock = new Clocking();
                    $clock->setWeekRef($year.$week);
                    $clock->setDay($days[$i]);
                    $clock->setPartOfDay('Morning');
                    $clock->setClockingHour($daysData[$i]['Morning']);
                    $clock->setUser($user);
                    $entityManager->persist($clock);
                }
                if ($daysData[$i]['Lunch'] != null){
                    $clock = new Clocking();
                    $clock->setWeekRef($year.$week);
                    $clock->setDay($days[$i]);
                    $clock->setPartOfDay('Lunch');
                    $clock->setClockingHour($daysData[$i]['Lunch']);
                    $clock->setUser($user);
                    $entityManager->persist($clock);
                }
                if ($daysData[$i]['Afternoon'] != null){
                    $clock = new Clocking();
                    $clock->setWeekRef($year.$week);
                    $clock->setDay($days[$i]);
                    $clock->setPartOfDay('Afternoon');
                    $clock->setClockingHour($daysData[$i]['Afternoon']);
                    $clock->setUser($user);
                    $entityManager->persist($clock);
                }
                if ($daysData[$i]['Evening'] != null){
                    $clock = new Clocking();
                    $clock->setWeekRef($year.$week);
                    $clock->setDay($days[$i]);
                    $clock->setPartOfDay('Evening');
                    $clock->setClockingHour($daysData[$i]['Evening']);
                    $clock->setUser($user);
                    $entityManager->persist($clock);
                }
            }
        }

        $entityManager->flush();

        // alert message
        $this->addFlash('success', $message);
        return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
    }

    #[Route('/home/dayoff/{year}/{week}', name: 'dayOff')]
    public function dayOff(EntityManagerInterface $entityManager, Request $request, string $year = null, string $week = null, Security $security): Response{
        $day = $request->request->get('dayOffBtn');
        $arrayDays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');

        // get the connected user and get his id
        $user = $security->getUser();
        $userId = $user->getId();

        $clocking = $entityManager->getRepository(Clocking::class)->findBy(['week_ref' => $year.$week, 'day' => $arrayDays[$day], 'user' => $userId]);
        if ($clocking){
            // drop all data with week_ref = $year.$week
            foreach ($clocking as $clock){
                $entityManager->remove($clock);
            }
        }
        // look into dayoff table if already exist a dayoff for this week and this day
        $dayOff = $entityManager->getRepository(DayOff::class)->findOneBy(['week_ref' => $year.$week, 'day' => $day, 'user' => $userId]);
        if ($dayOff){
            // drop all data with week_ref = $year.$week
            $entityManager->remove($dayOff);

        }else{
            // insert new data with week_ref = $year.$week annd day = $day in day_off table
            $dayOff = new DayOff();
            $dayOff->setWeekRef($year.$week);
            $dayOff->setDay($day);
            $dayOff->setUser($user);
            $entityManager->persist($dayOff);
        }

        $entityManager->flush();
        return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);

    }

    #[Route('/calendar', name: 'calendar')]
    public function calendar(): Response{
        return $this->render('clocking/calendar.html.twig', [
        ]);
    }

}