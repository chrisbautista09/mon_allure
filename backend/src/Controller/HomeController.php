<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $demoPlan = [

            'title' => 'Plan Découverte 10 km',

            'weeks' => [

                [
                    'number' => 1,
                    'sessions' => [

                        [
                            'day' => 'Lundi',
                            'title' => 'Footing endurance',
                            'duration' => '45 min',
                            'zone' => 'Z2'
                        ],

                        [
                            'day' => 'Mercredi',
                            'title' => 'Fractionné court',
                            'duration' => '50 min',
                            'zone' => 'Z4'
                        ],

                        [
                            'day' => 'Dimanche',
                            'title' => 'Sortie longue',
                            'duration' => '1h15',
                            'zone' => 'Z2'
                        ]

                    ]
                ],

                [
                    'number' => 2,
                    'sessions' => [

                        [
                            'day' => 'Mardi',
                            'title' => 'Footing récupération',
                            'duration' => '40 min',
                            'zone' => 'Z1'
                        ],

                        [
                            'day' => 'Jeudi',
                            'title' => 'Seuil',
                            'duration' => '55 min',
                            'zone' => 'Z3'
                        ]

                    ]
                ]
            ]
        ];

        return $this->render('home/index.html.twig',[
            'demoPlan'=>$demoPlan
        ]);
    }
}