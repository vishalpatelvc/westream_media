<?php
/**
 * Created by PhpStorm.
 * User: devel
 * Date: 3/17/2019
 * Time: 11:55 AM
 */
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{

    private function getCurrentUser(){
        $currentUser = $this->get('session')->get('user');
        return $currentUser;
    }

    /**
     * @Route("/")
     */
    public function view()
    {
        $toRedirect = $this->getParameter('toRedirect');
        if ($toRedirect) {
            $url = $this->getParameter('redrect_url');
            if ($url) {
                return $this->redirect($url, 308);
            }
        }

        return $this->render('home/home.html.twig', [
            'section' => 'home',
            'currentUser' => $this->getCurrentUser(),
        ]);
    }
}