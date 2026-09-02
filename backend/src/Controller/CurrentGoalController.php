<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TrainingPlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CurrentGoalController extends AbstractController
{
    #[Route('/objective', name: 'app_current_goal', methods: ['GET'])]
    public function show(TrainingPlanRepository $trainingPlanRepository): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $this->render('goal/current.html.twig', [
            'plan' => $trainingPlanRepository->findLatestActiveOwned($user),
        ]);
    }
}
