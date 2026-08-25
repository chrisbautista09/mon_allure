<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();

        if ($user instanceof User) {
            if ($user->getProfile() === null) {
                return $this->redirectToRoute('app_profile_calibration');
            }

            foreach ($user->getTrainingPlans() as $plan) {
                if ($plan->isActive()) {
                    return $this->redirectToRoute('app_training_weekly');
                }
            }

            return $this->redirectToRoute('app_training_goal');
        }

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
