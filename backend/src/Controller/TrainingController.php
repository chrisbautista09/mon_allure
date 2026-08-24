<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingController extends AbstractController
{
    #[Route(
        '/training/weekly',
        name: 'app_training_weekly',
        methods: ['GET']
    )]
    public function weekly(TrainingPlanRepository $repository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        $plan = $repository->findLatestActiveOwnedWithSessions($user);
        $weeks = [];

        if ($plan !== null) {
            foreach ($plan->getSessions() as $session) {
                $week = (int) $session->getWeekIndex();
                $weeks[$week] ??= [];
                $weeks[$week][] = $session;
            }

            ksort($weeks);
        }

        return $this->render('training/weekly.html.twig', [
            'plan' => $plan,
            'weeks' => $weeks,
        ]);
    }

    #[Route(
        '/training/daily/{id}',
        name: 'app_training_daily',
        requirements: ['id' => '\d+'],
        methods: ['GET']
    )]
    public function daily(int $id): Response
    {
        $sessions = [
            1 => [
                'date' => 'Lundi 6 juillet 2026',
                'type' => 'Repos',
                'title' => 'Repos complet',
                'description' => 'Journée d’assimilation destinée à favoriser la récupération après les séances précédentes.',
            ],
            2 => [
                'date' => 'Mardi 7 juillet 2026',
                'type' => 'Séance de VMA',
                'title' => 'VMA courte – 2 × 10 × 30"/30"',
                'description' => 'Échauffement de 20 minutes, suivi de deux séries de 10 accélérations de 30 secondes avec 30 secondes de récupération active. Retour au calme de 10 minutes.',
            ],
            3 => [
                'date' => 'Mercredi 8 juillet 2026',
                'type' => 'Endurance fondamentale',
                'title' => 'Footing de récupération',
                'description' => 'Footing souple de 45 minutes en zone 1 ou zone 2, sans recherche de vitesse.',
            ],
            4 => [
                'date' => 'Jeudi 9 juillet 2026',
                'type' => 'Séance de côtes',
                'title' => 'Côtes courtes – 10 × 30 secondes',
                'description' => 'Échauffement progressif, puis 10 répétitions en côte avec récupération en descente. Retour au calme de 10 minutes.',
            ],
            5 => [
                'date' => 'Vendredi 10 juillet 2026',
                'type' => 'Récupération',
                'title' => 'Repos ou étirements',
                'description' => 'Repos complet ou séance légère de mobilité et d’étirements sans douleur.',
            ],
            6 => [
                'date' => 'Samedi 11 juillet 2026',
                'type' => 'Endurance',
                'title' => 'Footing plaisir',
                'description' => 'Course facile de 50 minutes à allure confortable, en restant capable de parler.',
            ],
            7 => [
                'date' => 'Dimanche 12 juillet 2026',
                'type' => 'Sortie longue',
                'title' => 'Sortie longue avec allure cible',
                'description' => 'Sortie longue de 1 h 40 comprenant deux blocs de 15 minutes à allure cible, séparés par 5 minutes en endurance fondamentale.',
            ],
        ];
    
        if (!isset($sessions[$id])) {
            throw $this->createNotFoundException(
                'La séance demandée n’existe pas.'
            );
        }
    
        return $this->render('training/daily.html.twig', [
            'session' => $sessions[$id],
        ]);
    }
}
