<?php

namespace App\Controller;

use App\Dto\TrainingPlanDTO;
use App\Form\TrainingGoalType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TrainingGoalController extends AbstractController
{
    #[Route('/training-goal', name: 'app_training_goal', methods: ['GET', 'POST'])]
    public function define(Request $request): Response
    {
        $goal = new TrainingPlanDTO();
        $form = $this->createForm(TrainingGoalType::class, $goal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set('training_goal', $goal->toArray());
            $this->addFlash('success', 'Votre objectif est prêt pour la génération du plan.');

            return $this->redirectToRoute('app_training_goal');
        }

        return $this->render('training_goal/define.html.twig', [
            'trainingGoalForm' => $form,
            'savedGoal' => $request->getSession()->get('training_goal'),
        ]);
    }
}
