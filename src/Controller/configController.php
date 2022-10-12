<?php

namespace App\Controller;

use App\Entity\Conf;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class configController extends AbstractController
{
    #[Route('/config')]
    public function config(EntityManagerInterface $entityManager): Response{
        // check if entry in Conf table
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
            // if "Mon" is in array
            if (in_array("Mon", $exceptionDays)){
                $mon = "checked";
            }
            else{
                $mon = "";
            }
            // if "Tue" is in array
            if (in_array("Tue", $exceptionDays)){
                $tue = "checked";
            }
            else{
                $tue = "";
            }
            // if "Wed" is in array
            if (in_array("Wed", $exceptionDays)){
                $wed = "checked";
            }
            else{
                $wed = "";
            }
            // if "Thu" is in array
            if (in_array("Thu", $exceptionDays)){
                $thu = "checked";
            }
            else{
                $thu = "";
            }
            // if "Fri" is in array
            if (in_array("Fri", $exceptionDays)){
                $fri = "checked";
            }
            else{
                $fri = "";
            }

            $entityManager->flush();
            return $this->render('config/config.html.twig', [
                'config' => $config,
                'lunchBreakTime' => $lunchBreakTime,
                'hoursToDoInWeek' => $hoursToDoInWeek,
                'exceptionTime' => $exceptionTime,
                'mon' => $mon,
                'tue' => $tue,
                'wed' => $wed,
                'thu' => $thu,
                'fri' => $fri,
            ]);
        }

    }

}