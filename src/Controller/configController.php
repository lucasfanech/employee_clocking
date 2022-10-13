<?php

namespace App\Controller;

use App\Entity\Conf;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class configController extends AbstractController
{
    #[Route('/config', name: 'configPage')]
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


    #[Route('/config/save', name: 'config_save')]
    public function save(EntityManagerInterface $entityManager, Request $request): Response{
        // get POST data
        $lunchBreakTime = $request->request->get('lunchBreakTime');
        $h_hoursToDoInWeek = $request->request->get('h_hoursToDoInWeek');
        $m_hoursToDoInWeek = $request->request->get('m_hoursToDoInWeek');
        $hoursToDoInWeek = $h_hoursToDoInWeek . ":" . $m_hoursToDoInWeek.":00";
        $exceptionTime = $request->request->get('exceptionTime');
        $mon = $request->request->get('mon');
        $tue = $request->request->get('tue');
        $wed = $request->request->get('wed');
        $thu = $request->request->get('thu');
        $fri = $request->request->get('fri');

        $exceptionDays = "";
        if (!is_null($mon)){
            $exceptionDays .= "Mon;";
        }
        if (!is_null($tue)){
            $exceptionDays .= "Tue;";
        }
        if (!is_null($wed)){
            $exceptionDays .= "Wed;";
        }
        if (!is_null($thu)){
            $exceptionDays .= "Thu;";
        }
        if (!is_null($fri)){
            $exceptionDays .= "Fri;";
        }

        // check if entry in Conf table
        $conf = $entityManager->getRepository(Conf::class)->findAll();
        if (!$conf){
            // create new entry
            $config = new Conf();
            $config->setTimeLunchBreak(new \DateTime($lunchBreakTime));
            $config->setTimeHoursToDoWeek($hoursToDoInWeek);
            $config->setTimeException(new \DateTime($exceptionTime));
            $config->setDaysException($exceptionDays);
            $entityManager->persist($config);
            $entityManager->flush();
            $this->addFlash('success', 'Configuration saved!');

        }
        else{
            // update existing entry
            $conf[0]->setTimeLunchBreak(new \DateTime($lunchBreakTime));
            $conf[0]->setTimeHoursToDoWeek($hoursToDoInWeek);
            $conf[0]->setTimeException(new \DateTime($exceptionTime));
            $conf[0]->setDaysException($exceptionDays);
            $entityManager->flush();
            $this->addFlash('success', 'Configuration saved!');

        }
        return $this->redirectToRoute('configPage');
//        return $this->render('config/config.html.twig', [
//            'config' => 1,
//            'lunchBreakTime' => $lunchBreakTime,
//            'hoursToDoInWeek' => $hoursToDoInWeek,
//            'exceptionTime' => $exceptionTime,
//            'mon' => $mon,
//            'tue' => $tue,
//            'wed' => $wed,
//            'thu' => $thu,
//            'fri' => $fri,
//        ]);


    }

}