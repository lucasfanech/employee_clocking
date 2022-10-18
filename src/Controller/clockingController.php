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

class clockingController extends AbstractController
{
    #[Route('/home/{year}/{week}', name: 'home')]
    public function homepage(string $year = null, string $week = null, EntityManagerInterface $entityManager): Response{
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
                    $date->modify('+1 day');
                    $days[$i]['date'] = $date->format('Y-m-d');
                    $days[$i]['name'] = $daysName[$i];

                    // loop of 4 parts of day
                    for ($j = 0; $j < 4; $j++){
                        // get from DB table clocking where week_ref == $year.$week and day == $daysName[$i] and partOfDay == $partOfDay[$j]
                        $clocking = $entityManager->getRepository(Clocking::class)->findBy(array('week_ref' => $year.$week, 'day' => $daysName[$i], 'partOfDay' => $partOfDay[$j]));
                        if ($clocking){
                            $days[$i][$partOfDay[$j]] = $clocking[0]->getClockingHour()->format('H:i');
                        }
                        else{
                            $days[$i][$partOfDay[$j]] = "";
                        }


                    }




                }

                // get configuration
                $conf = $entityManager->getRepository(Conf::class)->findAll();
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
                $daysOff = $entityManager->getRepository(DayOff::class)->findBy(array('week_ref' => $year.$week));
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


            }


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

        ]);

    }

    #[Route('/home/save/{year}/{week}', name: 'clocking_save')]
    public function save(EntityManagerInterface $entityManager, Request $request, string $year = null, string $week = null): Response{
        $message = "All clocking data saved";
        // POST data
        $data = $request->request->all();

        //put in array days : 'Morning': $data['c1_0'], 'Lunch': $data['c2_0'], 'Afternoon': $data['c3_0'], 'Evening': $data['c4_0']
        $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');
        $daysData = array();
        for ($i = 0; $i < 5; $i++){
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

        // SAVE DATA
        // check every entries in clocking table with week_ref = $year.$week
        $clocking = $entityManager->getRepository(Clocking::class)->findBy(['week_ref' => $year.$week]);
        if ($clocking){
            // drop all data with week_ref = $year.$week
            foreach ($clocking as $clock){
                $entityManager->remove($clock);
            }
        }
        // insert new data with week_ref = $year.$week and days data FOR EACH DAY OF THE WEEK
        for ($i = 0; $i < 5; $i++){
            // insert new data Monday
            if ($daysData[$i]['Morning'] != null){
                $clock = new Clocking();
                $clock->setWeekRef($year.$week);
                $clock->setDay($days[$i]);
                $clock->setPartOfDay('Morning');
                $clock->setClockingHour($daysData[$i]['Morning']);
                $entityManager->persist($clock);
            }
            if ($daysData[$i]['Lunch'] != null){
                $clock = new Clocking();
                $clock->setWeekRef($year.$week);
                $clock->setDay($days[$i]);
                $clock->setPartOfDay('Lunch');
                $clock->setClockingHour($daysData[$i]['Lunch']);
                $entityManager->persist($clock);
            }
            if ($daysData[$i]['Afternoon'] != null){
                $clock = new Clocking();
                $clock->setWeekRef($year.$week);
                $clock->setDay($days[$i]);
                $clock->setPartOfDay('Afternoon');
                $clock->setClockingHour($daysData[$i]['Afternoon']);
                $entityManager->persist($clock);
            }
            if ($daysData[$i]['Evening'] != null){
                $clock = new Clocking();
                $clock->setWeekRef($year.$week);
                $clock->setDay($days[$i]);
                $clock->setPartOfDay('Evening');
                $clock->setClockingHour($daysData[$i]['Evening']);
                $entityManager->persist($clock);
            }

        }

        $entityManager->flush();

        // alert message
        $this->addFlash('success', $message);
        return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);
    }

    #[Route('/home/dayoff/{year}/{week}', name: 'dayOff')]
    public function dayOff(EntityManagerInterface $entityManager, Request $request, string $year = null, string $week = null): Response{
        $day = $request->request->get('dayOffBtn');
        $arrayDays = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');


        $clocking = $entityManager->getRepository(Clocking::class)->findBy(['week_ref' => $year.$week, 'day' => $arrayDays[$day]]);
        if ($clocking){
            // drop all data with week_ref = $year.$week
            foreach ($clocking as $clock){
                $entityManager->remove($clock);
            }
        }
        // look into dayoff table if already exist a dayoff for this week and this day
        $dayOff = $entityManager->getRepository(DayOff::class)->findOneBy(['week_ref' => $year.$week, 'day' => $day]);
        if ($dayOff){
            // drop all data with week_ref = $year.$week
            $entityManager->remove($dayOff);

        }else{
            // insert new data with week_ref = $year.$week annd day = $day in day_off table
            $dayOff = new DayOff();
            $dayOff->setWeekRef($year.$week);
            $dayOff->setDay($day);
            $entityManager->persist($dayOff);
        }

        $entityManager->flush();
        return $this->redirectToRoute('home', ['year' => $year, 'week' => $week]);

    }

    #[Route('/calendar')]
    public function calendar(): Response{
        return $this->render('clocking/calendar.html.twig', [
        ]);
    }

}