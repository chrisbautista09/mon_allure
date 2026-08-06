<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PersonaController extends AbstractController
{
    #[Route('/personas', name: 'app_personas', methods: ['GET'])]
    public function index(): Response
    {
        $personas = [
            [
                'initials' => 'SM',
                'name' => 'Sophie Martin',
                'age' => 38,
                'title' => 'Retrouver la forme',
                'level' => 'Découverte',
                'levelKey' => 'discovery',
                'objective' => 'Courir 40 min sans arrêt',
                'experience' => 'Débutante · 2 sorties/semaine',
                'profile' => 'VMA 9,5 km/h · FCM 182 bpm',
                'need' => 'Une progression douce, simple et motivante.',
                'icon' => '♥',
                'accent' => 'cyan',
            ],
            [
                'initials' => 'KB',
                'name' => 'Karim Benali',
                'age' => 31,
                'title' => 'Finir son premier semi',
                'level' => 'Intermédiaire',
                'levelKey' => 'intermediate',
                'objective' => 'Parcourir 21,1 km en continu',
                'experience' => '18 mois · Sortie max. 14 km',
                'profile' => 'VMA 13 km/h · FCM 189 bpm',
                'need' => 'Développer son endurance sans viser de chrono.',
                'icon' => '21',
                'accent' => 'blue',
            ],
            [
                'initials' => 'JL',
                'name' => 'Julien Lefèvre',
                'age' => 44,
                'title' => 'Marathon en moins de 4 h',
                'level' => 'Performance',
                'levelKey' => 'performance',
                'objective' => '42,195 km en 3 h 59',
                'experience' => '6 ans · Marathon en 4 h 12',
                'profile' => 'VMA 15,5 km/h · FCM 178 bpm',
                'need' => 'Tenir 5 min 41 s/km et gérer sa charge.',
                'icon' => '4h',
                'accent' => 'violet',
            ],
            [
                'initials' => 'ED',
                'name' => 'Élodie Dubois',
                'age' => 35,
                'title' => 'Découvrir le trail',
                'level' => 'Intermédiaire',
                'levelKey' => 'intermediate',
                'objective' => '25 km avec 1 200 m D+',
                'experience' => '3 ans sur route · 1er trail',
                'profile' => 'VMA 13,5 km/h · FCM 185 bpm',
                'need' => 'Apprivoiser les côtes, descentes et efforts longs.',
                'icon' => '▲',
                'accent' => 'green',
            ],
        ];

        return $this->render('persona/index.html.twig', [
            'personas' => $personas,
        ]);
    }
}
