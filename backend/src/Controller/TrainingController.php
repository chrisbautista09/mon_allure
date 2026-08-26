<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Performance;
use App\Entity\User;
use App\Form\PerformanceType;
use App\Repository\PerformanceRepository;
use App\Repository\SessionRepository;
use App\Repository\TrainingPlanRepository;
use App\Service\AdaptationService;
use App\Service\FormStatusService;
use App\Service\ObjectiveCountdownService;
use App\Service\ProgressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrainingController extends AbstractController
{
    #[Route('/training/weekly', name: 'app_training_weekly', methods: ['GET'])]
    public function weekly(
        TrainingPlanRepository $repository,
        ProgressService $progressService,
        ObjectiveCountdownService $countdownService,
        FormStatusService $formStatusService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->authenticatedUser();

        if ($user->getProfile() === null) {
            return $this->redirectToRoute('app_profile_calibration');
        }

        $plan = $repository->findLatestActiveOwnedWithSessions($user);

        if ($plan === null) {
            return $this->redirectToRoute('app_training_goal');
        }

        if ($progressService->synchronizeCurrentWeek($plan)) {
            $entityManager->flush();
        }

        $weeks = [];

        foreach ($plan->getSessions() as $session) {
            $week = (int) $session->getWeekIndex();
            $weeks[$week] ??= [];
            $weeks[$week][] = $session;

        }

        ksort($weeks);

        return $this->render('training/weekly.html.twig', [
            'plan' => $plan,
            'weeks' => $weeks,
            'progressPercentage' => $progressService->calculatePlanProgress($plan),
            'sportsProgress' => $progressService->getSportsProgress($plan),
            'currentPhase' => $progressService->getCurrentPhase($plan),
            'isPlanCompleted' => $progressService->isPlanCompleted($plan),
            'countdown' => $countdownService->calculateRemainingTime($plan),
            'timelineComparison' => $countdownService->calculateTimelineComparison($plan),
            'formStatus' => $formStatusService->calculate($user),
        ]);
    }

    #[Route(
        '/training/daily/{id}',
        name: 'app_training_daily',
        requirements: ['id' => '\d+'],
        methods: ['GET', 'POST'],
    )]
    public function daily(
        int $id,
        Request $request,
        SessionRepository $sessionRepository,
        PerformanceRepository $performanceRepository,
        AdaptationService $adaptationService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->authenticatedUser();
        $session = $sessionRepository->findOneOwned($id, $user);

        if ($session === null) {
            throw $this->createNotFoundException('La séance demandée n’existe pas.');
        }

        $recordedPerformance = $performanceRepository->findOneBySessionAndUser($session, $user);
        $performance = $recordedPerformance ?? (new Performance())
            ->setSession($session)
            ->setUser($user);
        $form = $this->createForm(PerformanceType::class, $performance);
        $form->handleRequest($request);

        if ($recordedPerformance === null && $form->isSubmitted() && $form->isValid()) {
            $terrainType = $form->get('terrainType')->getData();
            $plannedTerrain = $session->getTrainingPlan()?->getTerrainType();

            if ($terrainType !== $plannedTerrain) {
                $form->get('terrainType')->addError(new FormError(
                    'Le terrain doit correspondre à celui du plan d’entraînement.',
                ));
            } else {
                $session->setStatus('completed');
                $adaptation = $adaptationService->adapt($performance);
                $entityManager->persist($performance);
                $entityManager->flush();
                $this->addFlash('success', sprintf(
                    'Votre performance a bien été enregistrée. Évaluation : %s.',
                    $adaptation->evaluation->result->value,
                ));

                return $this->redirectToRoute('app_training_daily', ['id' => $id]);
            }
        }

        return $this->render('training/daily.html.twig', [
            'session' => $session,
            'performance' => $recordedPerformance,
            'performanceForm' => $form,
        ], $form->isSubmitted() && !$form->isValid() ? new Response(status: 422) : null);
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }
}
