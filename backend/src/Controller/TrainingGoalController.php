<?php

namespace App\Controller;

use App\Dto\TrainingPlanDTO;
use App\Entity\User;
use App\Form\TrainingGoalType;
use App\Repository\TrainingPlanRepository;
use App\Service\TrainingPlanGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TrainingGoalController extends AbstractController
{
    #[Route('/training-goal', name: 'app_training_goal', methods: ['GET', 'POST'])]
    public function define(
        Request $request,
        TrainingPlanGeneratorService $generator,
        TrainingPlanRepository $trainingPlanRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        if ($user->getProfile() === null) {
            $this->addFlash('info', 'Complétez votre profil avant de définir votre objectif.');

            return $this->redirectToRoute('app_profile_calibration');
        }

        $goal = new TrainingPlanDTO();
        $form = $this->createForm(TrainingGoalType::class, $goal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->wrapInTransaction(function () use (
                    $generator,
                    $user,
                    $goal,
                    $trainingPlanRepository,
                    $entityManager,
                ): void {
                    foreach ($trainingPlanRepository->findBy([
                        'user' => $user,
                        'isActive' => true,
                    ]) as $activePlan) {
                        $activePlan->setIsActive(false);
                    }

                    $entityManager->persist($generator->generatePlan($user, $goal));
                });
            } catch (\DomainException|\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return $this->render('training_goal/define.html.twig', [
                    'trainingGoalForm' => $form,
                    'savedGoal' => null,
                ], new Response(status: 422));
            }

            $request->getSession()->remove('training_goal');
            $this->addFlash('success', 'Votre plan d’entraînement a bien été généré.');

            return $this->redirectToRoute('app_training_weekly');
        }

        return $this->render('training_goal/define.html.twig', [
            'trainingGoalForm' => $form,
            'savedGoal' => $request->getSession()->get('training_goal'),
        ]);
    }
}
