<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class clockingController extends AbstractController
{
    #[Route('/home/{year}/{week}')]
    public function homepage(string $year = null, string $week = null): Response{
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
                $date->modify('monday this week');
                for ($i = 0; $i < 5; $i++){
                    $date->modify('+1 day');
                    $days[$i]['date'] = $date->format('Y-m-d');
                    $days[$i]['name'] = $daysName[$i];

                }


            }


        }
        return $this->render('clocking/homepage.html.twig', [
            'title' => 'Clocking',
            'message' => $message,
            'message2' => $message2,
            'week' => $week,
            'days' => $days,
        ]);

    }

    #[Route('/calendar')]
    public function calendar(): Response{
        return $this->render('clocking/calendar.html.twig', [
        ]);
    }

}